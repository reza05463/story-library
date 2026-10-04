"""Integration checks using an isolated disposable MySQL instance (no installs).

python tests/integration.py --php /path/to/php --mysql-bin /path/to/mysql/bin
Requires Python 3, PHP with pdo_mysql/mbstring/gd, and MySQL 8 binaries.
Creates new temporary data only. Never connects to an existing database.
Stops both child servers; retains the test directory for inspection.
"""
import argparse, http.cookiejar, json, os, re, socket, subprocess, tempfile, time
from pathlib import Path
from urllib import request, parse, error

args=argparse.ArgumentParser()
args.add_argument('--php',required=True)
args.add_argument('--mysql-bin',required=True)
opts=args.parse_args()
repo=Path(__file__).resolve().parents[1]
work=Path(tempfile.mkdtemp(prefix='story-library-test-'))
data=work/'data'; sessions=work/'sessions'; sessions.mkdir()
ext='.exe' if os.name=='nt' else ''
mysql=Path(opts.mysql_bin)/('mysql'+ext); mysqld=Path(opts.mysql_bin)/('mysqld'+ext)
flags={'creationflags':subprocess.CREATE_NO_WINDOW} if os.name=='nt' else {}
def port():
    with socket.socket() as s: s.bind(('127.0.0.1',0)); return s.getsockname()[1]
dbport=port(); httpport=port()
def sql(query, database=False):
    command=[str(mysql),'--no-defaults','--protocol=TCP','-h127.0.0.1',f'-P{dbport}','-uroot','--batch','--skip-column-names','--default-character-set=utf8mb4']
    if database: command+=['library']
    r=subprocess.run(command,input=query,encoding='utf-8',capture_output=True,**flags)
    if r.returncode: raise RuntimeError(r.stderr)
    return r.stdout.strip()
class Client:
    def __init__(self):
        self.cookies=http.cookiejar.CookieJar()
        self.opener=request.build_opener(request.HTTPCookieProcessor(self.cookies))
    def call(self,path,values=None):
        body=parse.urlencode(values).encode() if values is not None else None
        try:
            with self.opener.open(f'http://127.0.0.1:{httpport}/'+path,body,timeout=10) as r: return r.status,r.read().decode('utf-8',errors='replace')
        except error.HTTPError as e: return e.code,e.read().decode('utf-8',errors='replace')
    def token(self,path='user.php'):
        status,html=self.call(path)
        assert status==200,(status,html)
        return re.search(r'name="csrf" value="([a-f0-9]+)"',html).group(1)
    def signup(self,email,tel):
        token=self.token('login.php'); self.call('captcha.php?type=signup')
        sid=next(c.value for c in self.cookies if c.name=='PHPSESSID')
        # The fixture reads its own isolated session file, not a production CAPTCHA bypass.
        saved=(sessions/('sess_'+sid)).read_text()
        captcha=re.search(r'"signup";s:6:"([a-z]+)"',saved).group(1)
        status,html=self.call('login.php',dict(csrf=token,action='signup',name='<b>Test</b>',family='Author',tel=tel,email=email,password='Example-test-Password!',captcha=captcha))
        assert status==200 and 'edit_profile' in html,(status,html)
        return int(sql("SELECT id FROM users WHERE email='"+email+"'",True))
processes=[]; logs=[]
try:
    init=subprocess.run([str(mysqld),'--no-defaults','--initialize-insecure',f'--datadir={data}'],capture_output=True,timeout=90,**flags)
    assert init.returncode==0,init.stderr.decode(errors='replace')
    log=(work/'mysql.log').open('wb'); logs.append(log)
    processes.append(subprocess.Popen([str(mysqld),'--no-defaults',f'--datadir={data}',f'--port={dbport}','--bind-address=127.0.0.1','--mysqlx=0','--skip-log-bin'],stdout=log,stderr=log,**flags))
    for _ in range(60):
        try: sql('SELECT 1'); break
        except RuntimeError: time.sleep(.5)
    else: raise RuntimeError('Isolated MySQL did not start: '+str(work))
    sql('CREATE DATABASE library CHARACTER SET utf8mb4')
    sql((repo/'database/schema.sql').read_text(encoding='utf-8'),True)
    env=os.environ.copy(); env.update(DB_HOST='127.0.0.1',DB_PORT=str(dbport),DB_NAME='library',DB_USER='root',DB_PASS='')
    log=(work/'php.log').open('wb'); logs.append(log)
    processes.append(subprocess.Popen([opts.php,'-d',f'session.save_path={sessions}','-d','display_errors=0','-S',f'127.0.0.1:{httpport}','-t',str(repo/'public')],env=env,stdout=log,stderr=log,**flags))
    visitor=Client()
    for _ in range(40):
        try: assert visitor.call('index.php')[0]==200; break
        except (error.URLError,AssertionError): time.sleep(.25)
    else: raise RuntimeError('PHP server did not start.')
    a=Client(); aid=a.signup('author@example.test','100001')
    b=Client(); bid=b.signup('other@example.test','100002')
    token=a.token()
    story=dict(csrf=token,action='add_story',title='<script>alert(1)</script>',content='<img src=x onerror=alert(1)>',summary='',cover_image='',category_id='')
    assert a.call('write.php',story)[0]==200
    sid=int(sql('SELECT MAX(id) FROM stories',True))
    status,html=visitor.call(f'index.php?id={sid}')
    assert status==200 and '&lt;script&gt;' in html and '<script>alert' not in html and '&lt;img' in html
    before=sql(f'SELECT title FROM stories WHERE id={sid}',True)
    b.call(f'write.php?id={sid}',dict(story,csrf=b.token(),action='edit_story',title='Unauthorized'))
    b.call('user.php',dict(csrf=b.token(),delete=sid))
    assert sql(f'SELECT title FROM stories WHERE id={sid}',True)==before
    assert a.call('user.php',dict(delete=sid))[0]==403
    a.call(f'user.php?delete={sid}')
    assert sql(f'SELECT COUNT(*) FROM stories WHERE id={sid}',True)=='1'
    for fields in [dict(cover_image='../config.php'),dict(cover_image='a'*260+'.png'),dict(category_id='99999'),dict(title='   ')]:
        a.call('write.php',dict(story,**fields))
    assert sql('SELECT COUNT(*) FROM stories',True)=='1'
    assert visitor.call('index.php?id[]=1')[0]==400
    # Refreshing the role from the DB authorizes an existing session correctly.
    sql(f'UPDATE users SET role=1 WHERE id={aid}',True)
    assert a.call('admin.php')[0]==200
    a.call('admin.php',dict(csrf=a.token(),toggle=sid,status=0))
    assert visitor.call(f'index.php?id={sid}')[0]==404
    a.call('admin.php',dict(csrf=a.token(),toggle=sid,status=1))
    assert visitor.call(f'index.php?id={sid}')[0]==200
    a.call('user.php',dict(csrf=a.token(),action='edit_profile',name='Changed',family='Author',tel='100001',email='changed@example.test',current_password='wrong',password=''))
    assert sql(f'SELECT email FROM users WHERE id={aid}',True)=='author@example.test'
    a.call('user.php',dict(csrf=a.token(),action='edit_profile',name='Changed',family='Author',tel='100001',email='changed@example.test',current_password='Example-test-Password!',password=''))
    assert sql(f'SELECT email FROM users WHERE id={aid}',True)=='changed@example.test'
    a.call('admin.php',dict(csrf=a.token(),deleteuser=bid))
    assert sql(f'SELECT COUNT(*) FROM users WHERE id={bid}',True)=='0'
    assert 'edit_profile' not in b.call('user.php')[1]
    a.call('admin.php',dict(csrf=a.token(),delete=sid))
    assert sql('SELECT COUNT(*) FROM stories',True)=='0'
    a.call('index.php',dict(csrf=a.token(),logout=1))
    assert 'edit_profile' not in a.call('user.php')[1]
    token=a.token('login.php'); a.call('captcha.php?type=login')
    oldsid=next(c.value for c in a.cookies if c.name=='PHPSESSID')
    saved=(sessions/('sess_'+oldsid)).read_text()
    captcha=re.search(r'"login";s:6:"([a-z]+)"',saved).group(1)
    status,html=a.call('login.php',dict(csrf=token,action='login',email='changed@example.test',password='Example-test-Password!',captcha=captcha))
    assert status==200 and 'admin.php' in html
    assert next(c.value for c in a.cookies if c.name=='PHPSESSID')!=oldsid
    print(json.dumps({'status':'PASS','checks':['registration','escaped output','owner authorization','CSRF rejection','GET deletion ignored','cover/category/blank-title validation','array input rejected','admin role refresh','hide/publish','profile password check','user deletion','story deletion','logout','login and session-ID rotation'],'test_directory':str(work)}))
finally:
    for process in reversed(processes):
        process.terminate()
        try: process.wait(timeout=10)
        except subprocess.TimeoutExpired: process.kill(); process.wait()
    for log in logs: log.close()

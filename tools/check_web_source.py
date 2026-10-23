"""Offline synthetic checks for restored source and the loopback UI boundary."""
from pathlib import Path
import argparse, ast, http.client, json, os, re, socket, subprocess, tempfile, time
ROOT=Path(__file__).resolve().parents[1];WEB=ROOT/'web'

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--php',default='php');args=parser.parse_args()
    php=[args.php];ext=Path(args.php).resolve().parent/'ext'
    if os.name=='nt' and (ext/'php_sqlite3.dll').exists():php+=['-d','extension_dir='+str(ext),'-d','extension=sqlite3']
    def run(command):subprocess.run(command,cwd=ROOT,check=True,timeout=180)
    files=[p for p in WEB.rglob('*') if p.is_file() and not any(x in {'preview','__pycache__'} for x in p.relative_to(WEB).parts)]
    sensitive=[]
    for p in files:
        rel=p.relative_to(WEB)
        if rel.parts[0] in {'data','storage','uploads','user_memories','backups','vendor'}:sensitive.append(str(rel))
        if rel.parts[0]=='private' and rel.as_posix()!='private/config.example.php':sensitive.append(str(rel))
        if p.suffix.lower() in {'.db','.sqlite','.sqlite3','.pem','.key','.pfx','.zip','.tgz','.gz','.bak'}:sensitive.append(str(rel))
        if p.name.startswith('.env') and p.name!='.env.example':sensitive.append(str(rel))
    assert not sensitive,'Unexpected private/runtime files: '+str(sensitive)
    # Validate local asset references rather than relying on export extensions.
    for p in files:
        if p.suffix.lower() not in {'.php','.html','.css','.js'}:continue
        source=p.read_text(encoding='utf-8-sig',errors='replace')
        for match in re.finditer(r'''(?:src|href|poster)\s*=\s*["'](/assets/[^"'<>]+)["']''',source):
            asset=match.group(1).split('?')[0].split('#')[0]
            if any(c in asset for c in ['$', '{', '\\']):continue
            assert (WEB/asset.lstrip('/')).is_file(),f'Missing referenced asset {asset} in {p.relative_to(WEB)}'
    for p in files:
        if p.suffix=='.py':ast.parse(p.read_text(encoding='utf-8-sig'),filename=str(p))
    manifest=json.loads((WEB/'manifest.json').read_text(encoding='utf-8-sig'))
    for icon in manifest.get('icons',[]):
        asset=icon.get('src','')
        assert asset and '://' not in asset and (WEB/asset.lstrip('/')).is_file(),'PWA icon is missing or external'
    schema=(WEB/'sql/application-schema.sql').read_text(encoding='utf-8')
    assert not re.search(r'(?im)^\s*(INSERT|REPLACE|UPDATE|DELETE)\b',schema),'Database rows in schema'
    assert 'AUTO_INCREMENT=' not in schema,'Private row-counter state in schema'
    for p in files:
        if p.suffix=='.php':
            result=subprocess.run(php+['-l',str(p)],capture_output=True,text=True)
            if result.returncode:raise RuntimeError(result.stdout+result.stderr)
    for rel in ['tests/composition_interpretation.php','scripts/tests/test_zero_library_dna.php','scripts/tests/test_zero_library_privacy.php','tests/local-mode.php','scripts/tests/test_local_model_aliases.php']:run(php+[str(WEB/rel)])
    (WEB/'preview').mkdir(exist_ok=True)
    for name in ['discovery','ancestry','customer','country']:run(['node',str(WEB/'scripts'/('test_'+name+'.cjs'))])
    with tempfile.TemporaryDirectory(prefix='zerothink-public-check-') as temp:
        temp=Path(temp);env=os.environ.copy();env['ZEROTHINK_PRIVATE_STORAGE_ROOT']=str(temp/'storage')
        env['ZEROTHINK_OFFLOAD_URL']='http://127.0.0.1:1';env['ZEROTHINK_OFFLOAD_TOKEN']='synthetic-offload-token'
        output=temp/'report.md'
        command=php+[str(WEB/'scripts/dna-report.php'),str(WEB/'tests/synthetic-verification-v3-not-personal.txt'),str(output)]
        result=subprocess.run(command,env=env,capture_output=True,text=True,timeout=120)
        assert result.returncode==0,result.stdout+result.stderr
        text=output.read_text(encoding='utf-8');assert 'Full DNA Report' in text and 'not installed' in text
        result=subprocess.run(command,env=env,capture_output=True,text=True)
        assert result.returncode!=0 and output.read_text(encoding='utf-8')==text,'CLI overwrote report'
        with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
        with (temp/'server.log').open('wb') as log:
            server=subprocess.Popen(php+['-d','display_errors=0','-d','session.save_path="'+str(temp)+'"','-S',f'127.0.0.1:{port}',str(WEB/'local-router.php')],cwd=WEB,env=env,stdout=log,stderr=log)
            try:
                def request(path,headers=None,body=None):
                    con=http.client.HTTPConnection('127.0.0.1',port,timeout=30);con.request('POST' if body else 'GET',path,body,headers or {})
                    r=con.getresponse();out=(r.status,dict(r.getheaders()),r.read().decode('utf-8',errors='replace'));con.close();return out
                for attempt in range(50):
                    try:page=request('/dna');break
                    except OSError:time.sleep(.1)
                else:raise RuntimeError('Local server did not start')
                assert page[0]==200 and 'Local DNA Lab' in page[2]
                assert 'cdnjs.cloudflare.com' not in page[2],'Local startup loads remote CSS'
                for path in ['/app/bootstrap.php','/private/config.example.php','/sql/application-schema.sql','/api_vault.php','/%2e%2e/.env']:assert request(path)[0]==404,path
                assert request('/dna',{'Host':'evil.example'})[0]==403
                assert request('/dna',{'Origin':'https://evil.example'})[0]==403
                assert request('/dna',{'Sec-Fetch-Site':'cross-site'})[0]==403
                navigation={'Sec-Fetch-Site':'cross-site','Sec-Fetch-Mode':'navigate','Sec-Fetch-Dest':'document'}
                for path in ['/','/dna','/dna.php','/privacy','/terms','/faq']:
                    assert request(path,navigation)[0]==200,('Top-level read-only navigation blocked',path)
                assert request('/dna',{**navigation,'Origin':'https://evil.example'})[0]==403
                assert request('/dna',{**navigation,'Host':'evil.example'})[0]==403
                assert request('/dna',{**navigation,'Sec-Fetch-Dest':'iframe'})[0]==403
                assert request('/dna',{**navigation,'Sec-Fetch-Mode':'cors'})[0]==403
                assert request('/dna',{**navigation,'Content-Type':'application/json'},'{}')[0]==403
                assert request('/api_library.php',navigation)[0]==403
                assert request('/assets/maps/world-countries-110m.json',navigation)[0]==403
                assert request('/privacy')[0]==200
                status=request('/api_library.php',{'Content-Type':'application/json'},json.dumps({'action':'dna_status'}))
                assert status[0]==200 and json.loads(status[2])['status']=='success',status
                assert request('/api_library.php',{'Content-Type':'application/json'},json.dumps({'action':'dna_ncbi_search'}))[0]==403
                cookie=next((v for k,v in page[1].items() if k.lower()=='set-cookie'),'').split(';')[0]
                assert cookie,'Local page did not set its session cookie: '+str(list(page[1]))
                boundary='zerothink-synthetic-upload'
                def upload(token=None,consent='1'):
                    fields={'action':'dna_full_file','dna_consent':consent}
                    if token is not None:fields['csrf_token']=token
                    body=''.join('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+v+'\r\n' for k,v in fields.items())
                    raw=(WEB/'tests/synthetic-verification-v3-not-personal.txt').read_text(encoding='utf-8')
                    body+='--'+boundary+'\r\nContent-Disposition: form-data; name="dna_file"; filename="SYNTHETIC-only.txt"\r\nContent-Type: text/plain\r\n\r\n'+raw+'\r\n--'+boundary+'--\r\n'
                    return request('/api_library.php',{'Content-Type':'multipart/form-data; boundary='+boundary,'Cookie':cookie},body.encode())
                assert upload()[0]==403,'Upload without CSRF was accepted'
                token=json.loads(re.search(r'const DNA_CSRF_TOKEN\s*=\s*("[a-f0-9]+")',page[2]).group(1))
                consent_result=upload(token,'0')
                assert consent_result[0]==422,('Consent rejection status',consent_result)
                success=upload(token)
                assert success[0]==200 and json.loads(success[2])['status']=='success',success[:2]+(success[2][:500],)
                assert not list((temp/'storage').rglob('SYNTHETIC-only.txt')),'Raw upload was saved without opt-in'
            finally:server.terminate();server.wait(timeout=10)
    print(json.dumps({'status':'passed','php_files':sum(p.suffix=='.php' for p in files),'schema_rows':0,'fixtures':'synthetic only','local_router':'live loopback/origin/path/CSRF gates checked'}))
if __name__=='__main__':main()

import pathlib,subprocess,json
out=pathlib.Path(__file__).resolve().parent; root=out.parents[2]; rows=[]
for repeat in range(7):
 for mode in (['buffer','buffer-parse','string','string-parse'] if repeat%2 else ['string-parse','string','buffer-parse','buffer']):
  p=subprocess.run(['taskset','-c','2',str(root/'buildroot/bin/php'),str(out/'udp.php'),mode],capture_output=True,text=True,check=True)
  r=json.loads(p.stdout);r['repeat']=repeat;rows.append(r)
(out/'udp.json').write_text(json.dumps(rows,indent=2))

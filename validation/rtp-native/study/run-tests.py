#!/usr/bin/env python3
import pathlib,subprocess,json,os
root=pathlib.Path(__file__).resolve().parents[3]; out=pathlib.Path(__file__).resolve().parent
rows=[]
for label,binary in [('baseline',out/'php-baseline'),('interned',root/'buildroot/bin/php')]:
 tests=[('libspech/test_rtpc_native.php',root),('validation/rtp-native/functional.php',root),('validation/rtp-native/media.php',root),('validation/rtp-native/study/contracts.php',root),('validation/rtp-native/study/zts.php',root),('test_media_channel_member_ptime.php',root/'libspech'),('../validation/rtp-native/study/ptime-fixture.php',root/'libspech')]
 for script,cwd in tests:
  p=subprocess.run([str(binary),script],cwd=cwd,capture_output=True,text=True,timeout=60)
  rows.append(dict(binary=label,script=script,cwd=str(cwd),exit=p.returncode,stdout=p.stdout,stderr=p.stderr))
  print(label,script,p.returncode,flush=True)
(out/'tests.json').write_text(json.dumps(rows,indent=2))

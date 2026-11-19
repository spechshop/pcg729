#!/usr/bin/env python3
import os,sys,pathlib,subprocess,json,time,socket,signal,hashlib,select,statistics,argparse
OUT=pathlib.Path(__file__).resolve().parent;ROOT=OUT.parents[3];STUDY=OUT.parent
BINS={'baseline':STUDY/'php-baseline','optimized':ROOT/'buildroot/bin/php'}
HASH={'baseline':'352186d2e57558321df65b5ae32a9c21778f17f9087913542a29933ebf2a7e79','optimized':'18f0763f823d9e1b83ac3c78e9164dc0636772ceff173057550697a0314c6f99'}
SCENARIOS={'baseline-native':('baseline','native'),'optimized-native':('optimized','native'),'optimized-slice':('optimized','slice')}
def sha(p):return hashlib.sha256(pathlib.Path(p).read_bytes()).hexdigest()
def dump(p,d):p.write_text(json.dumps(d,indent=2))
def capture(cmd):return subprocess.check_output(cmd,text=True,stderr=subprocess.STDOUT,env=dict(os.environ,DEBUGINFOD_URLS=''),timeout=120)
def request(port,label):
 s=socket.socket(socket.AF_INET,socket.SOCK_DGRAM);s.settimeout(2)
 try:s.sendto(label.encode(),('127.0.0.1',port));return json.loads(s.recv(65535))
 finally:s.close()
def stop(p):
 if p is not None and p.poll() is None:
  p.terminate()
  try:p.wait(5)
  except subprocess.TimeoutExpired:p.kill();p.wait()
def run(name,scenario,seconds=60,profile=False,two=False):
 d=OUT/name
 if d.exists():raise RuntimeError('Refusing overwrite '+str(d))
 d.mkdir()
 label,mode=SCENARIOS[scenario];binary=BINS[label]
 assert sha(binary)==HASH[label], 'Binary mismatch'
 symbols=capture(['nm',str(binary)]);(d/'symbols.txt').write_text('\n'.join(x for x in symbols.splitlines() if any(k in x for k in ['rtp_parse','rtp_property','psampler_binary_with_view'])))
 assert 'zif_psampler_rtp_parse' in symbols
 assert ('rtp_property_names' in symbols)==(label=='optimized')
 api=capture([str(binary),'-r','echo PHP_VERSION," ZTS=",PHP_ZTS,"\\n"; echo new ReflectionFunction("psampler_rtp_parse"); echo new ReflectionMethod("Swoole\\Coroutine\\Socket","recvfromInto");'])
 (d/'api.txt').write_text(api)
 assert '8.5.12 ZTS=1' in api
 mc=ROOT/'libspech/plugins/Utils/sip/MediaChannel.php'
 assert '$this->socket->recvfromInto($nativeBuffer, $peer, 0.2)' in mc.read_text()
 paths=[binary,STUDY/'receiver.php',STUDY/'rtpc-slice.php',STUDY/'autoloader.php',OUT/'control.php',OUT/'sender.py',OUT/'run.py',mc,ROOT/'libspech/plugins/Utils/sip/rtpc.php',ROOT/'source/php-src/ext/psampler/binary_buffer.c',ROOT/'mc.php',ROOT/'lsend.php']
 manifest=dict(load_start=os.getloadavg(),frequency_start=pathlib.Path('/sys/devices/system/cpu/cpu2/cpufreq/scaling_cur_freq').read_text().strip(),scenario=scenario,binary=str(binary),binary_sha256=sha(binary),mode=mode,profile=profile,two_members=two,duration_requested=seconds,hashes={str(p.relative_to(ROOT)):sha(p) for p in paths},status='starting',kernel=capture(['uname','-a']),perf=capture(['/usr/bin/perf','--version']),paranoid=pathlib.Path('/proc/sys/kernel/perf_event_paranoid').read_text().strip(),affinity={'receiver':2,'sender':4},started_unix=time.time())
 env=dict(os.environ,RTP_STUDY_MODE=mode,RTP_PERF_CONTROL='1',RTP_PERF_TWO_MEMBERS='1' if two else '0',LC_ALL='C')
 rx=tx=perf=None;files=[];fds=[]
 try:
  for port in [40000,40001,50000,50001,50002]:
   with socket.socket(socket.AF_INET,socket.SOCK_DGRAM) as s:s.bind(('127.0.0.1',port))
  rlog=open(d/'receiver.log','w');tlog=open(d/'sender.log','w');plog=open(d/'perf.log','w');files=[rlog,tlog,plog]
  rcmd=['taskset','-c','2',str(binary),str(STUDY/'receiver.php')]
  tcmd=['taskset','-c','4',sys.executable,str(OUT/'sender.py')]
  manifest.update(receiver_command=rcmd,sender_command=tcmd)
  rx=subprocess.Popen(rcmd,cwd=ROOT,env=env,stdout=rlog,stderr=subprocess.STDOUT)
  time.sleep(.4)
  assert rx.poll() is None,'receiver exited'
  tx=subprocess.Popen(tcmd,cwd=ROOT,env=env,stdout=tlog,stderr=subprocess.STDOUT)
  time.sleep(5)
  assert rx.poll() is None and tx.poll() is None,'warmup failed'
  warm=request(40001,'warmup');dump(d/'warmup.json',warm)
  assert warm['rtp']>=230 and warm['real_packet_factory_verified'],'not stabilized'
  assert warm['binary_sha256']==HASH[label] and warm['pid']==rx.pid
  assert pathlib.Path(os.readlink(f'/proc/{rx.pid}/exe')).resolve()==binary.resolve()
  assert sha(f'/proc/{rx.pid}/exe')==HASH[label]
  assert warm['config']['receiveOnlyMode'] is False and warm['config']['nativeRtpReceive'] is True
  assert warm['config']['members']==(2 if two else 1)
  assert warm['config']['audioMetricsEnabled'] is False
  expected_file=STUDY/'rtpc-slice.php' if mode=='slice' else ROOT/'libspech/plugins/Utils/sip/rtpc.php'
  assert pathlib.Path(warm['factory_file']).resolve()==expected_file.resolve()
  manifest.update(pid=rx.pid,proc_exe=os.readlink(f'/proc/{rx.pid}/exe'),config=warm['config'],factory_file=warm['factory_file'],factory_sha256=warm['factory_sha256'])
  cr,cw=os.pipe();ar,aw=os.pipe();fds=[cr,cw,ar,aw]
  common=['-p',str(rx.pid),'-D','-1','--control',f'fd:{cr},{aw}']
  if profile:
   pcmd=['/usr/bin/perf','record','-o',str(d/'perf.data'),'-e','cpu_core/cycles/','-c','100000','--call-graph','dwarf,8192','-m','256']+common
  else:
   pcmd=['/usr/bin/perf','stat','-x',';','-o',str(d/'stat.csv'),'-e','task-clock,context-switches,cpu-migrations,cpu_core/cycles/,cpu_core/instructions/']+common
  manifest['perf_command']=pcmd;dump(d/'manifest.json',manifest)
  perf=subprocess.Popen(pcmd,cwd=ROOT,env=env,stdout=plog,stderr=subprocess.STDOUT,pass_fds=(cr,aw))
  time.sleep(.5)
  assert perf.poll() is None,'perf attach failed'
  def control(msg):
   os.write(cw,(msg+'\n').encode())
   if not select.select([ar],[],[],5)[0]:raise RuntimeError('perf ack timeout')
   ack=os.read(ar,128)
   assert b'ack' in ack,repr(ack)
   return time.monotonic_ns()
  t0=request(50001,'start');r0=request(40001,'start')
  enable=control('enable')
  target=time.monotonic()+seconds
  while time.monotonic()<target:
   time.sleep(min(1,max(0,target-time.monotonic())))
   assert rx.poll() is None and tx.poll() is None and perf.poll() is None,'process died during collection'
  disable=control('disable')
  r1=request(40001,'end');t1=request(50001,'end')
  perf.send_signal(signal.SIGINT);perf.wait(20)
  assert perf.returncode in (0,-signal.SIGINT),f'perf exit {perf.returncode}'
  manifest['perf_exit']=perf.returncode
  measurement=dict(start_receiver=r0,end_receiver=r1,start_sender=t0,end_sender=t1,enable_ack_ns=enable,disable_ack_ns=disable,active_seconds=(disable-enable)/1e9)
  dump(d/'measurement.json',measurement)
  delta={k:r1[k]-r0[k] for k in ['rtp','audio','dtmf_callbacks','dtmf_events']}
  delta['dtmf_packets']=delta['rtp']-delta['audio']
  delta['sender']={k:t1[k]-t0[k] for k in ['audio','dtmf','events','errors','late_slots','sink_pcmu','sink_pcmu_valid','sink_dtmf']}
  assert abs(delta['rtp']-seconds*50)<=2,delta
  assert abs(delta['rtp']-delta['sender']['audio']-delta['sender']['dtmf'])<=2,delta
  assert delta['sender']['errors']==0 and delta['sender']['late_slots']==0,delta
  if two:assert delta['sender']['sink_pcmu_valid']>seconds*35,delta
  manifest.update(status='complete',counts=delta,ended_unix=time.time(),load_end=os.getloadavg(),frequency_end=pathlib.Path('/sys/devices/system/cpu/cpu2/cpufreq/scaling_cur_freq').read_text().strip())
  stop(tx);stop(rx)
  if profile:
   for kind,opts in [('self',['--no-children']),('inclusive',['--children'])]:
    cmd=['/usr/bin/perf','report','--stdio','-i',str(d/'perf.data'),'--percent-limit','0.1','--sort','dso,symbol','--call-graph','none']+opts
    (d/('report-'+kind+'.txt')).write_text(capture(cmd));manifest['report_'+kind+'_command']=cmd
   (d/'script.txt').write_text(capture(['/usr/bin/perf','script','-i',str(d/'perf.data'),'-F','time,event,ip,sym,dso']))
  else:
   txt=(d/'stat.csv').read_text();assert '<not supported>' not in txt and '<not counted>' not in txt,txt
  print(name,delta,flush=True)
 except BaseException as e:
  manifest.update(status='failed',error=repr(e),ended_unix=time.time());raise
 finally:
  stop(perf);stop(tx);stop(rx)
  for f in files:f.close()
  for fd in fds:os.close(fd)
  dump(d/'manifest.json',manifest)
if __name__=='__main__':
 a=argparse.ArgumentParser();a.add_argument('--pilot',action='store_true');a.add_argument('--profiles',action='store_true');a.add_argument('--two',action='store_true');args=a.parse_args()
 if args.pilot:
  for i,s in enumerate(SCENARIOS):run(f'pilot-{i+1}',s,2,two=args.two)
 elif args.profiles:
  for i,s in enumerate(SCENARIOS):run(f'profile-{i+1}',s,60,True)
 elif args.two:
  for i,s in enumerate(SCENARIOS):run(f'two-{i+1}',s,60,two=True)
 else:
  orders=[['baseline-native','optimized-native','optimized-slice'],['optimized-slice','optimized-native','baseline-native'],['optimized-native','baseline-native','optimized-slice'],['optimized-slice','baseline-native','optimized-native'],['baseline-native','optimized-slice','optimized-native']]
  for i,order in enumerate(orders):
   for j,s in enumerate(order):run(f'stat-r{i+1}-{j+1}-{s}',s)

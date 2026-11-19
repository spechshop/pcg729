#!/usr/bin/env python3
import socket,time,struct,json,signal,selectors
running=True
def stop(*_):
 global running
 running=False
signal.signal(signal.SIGTERM,stop)
s=socket.socket(socket.AF_INET,socket.SOCK_DGRAM);s.bind(('127.0.0.1',50000))
ctrl=socket.socket(socket.AF_INET,socket.SOCK_DGRAM);ctrl.bind(('127.0.0.1',50001));ctrl.setblocking(False)
sink=socket.socket(socket.AF_INET,socket.SOCK_DGRAM);sink.bind(('127.0.0.1',50002));sink.setblocking(False)
sel=selectors.DefaultSelector();sel.register(ctrl,selectors.EVENT_READ);sel.register(sink,selectors.EVENT_READ)
state=dict(audio=0,dtmf=0,events=0,errors=0,late_slots=0,sink_pcma=0,sink_pcmu=0,sink_dtmf=0,sink_other=0,sink_pcmu_valid=0)
seq=0;slot=0;start=time.monotonic();deadline=start;last=start
print(json.dumps({'ready':True,'pid':__import__('os').getpid(),'schedule':'50 PPS; 11 DTMF slots at offset 50 of each 150; deterministic digit; PCMA D5 x160'}),flush=True)
while running:
 now=time.monotonic()
 if now>=deadline:
  phase=slot%150
  if 50<=phase<61:
   step=phase-50;digit=(slot//150)%10
   data=struct.pack('BBH',digit,10|(128 if step>=8 else 0),min(step+1,8)*160)
   packet=struct.pack('!BBHII',128,101|(128 if step==0 else 0),seq,((slot//150)*150+50)*160,123)+struct.pack('!BBH',digit,10|(128 if step>=8 else 0),min(step+1,8)*160)
   state['dtmf']+=1
   if step==0:state['events']+=1
  else:
   packet=struct.pack('!BBHII',128,8,seq,slot*160,123)+bytes([0xd5])*160;state['audio']+=1
  try:s.sendto(packet,('127.0.0.1',40000))
  except OSError:state['errors']+=1
  seq=(seq+1)&65535;slot+=1;deadline=start+slot*.02
  if now-deadline>.02:state['late_slots']+=1
  # Do not burst to catch up if delayed by more than a slot.
  if now-deadline>.02:start=now-slot*.02;deadline=now+.02
 if now-last>=1:
  print(json.dumps(dict(state,monotonic_ns=time.monotonic_ns())),flush=True);last=now
 for key,_ in sel.select(max(0,deadline-time.monotonic())):
  if key.fileobj is ctrl:
   msg,peer=ctrl.recvfrom(4096);ctrl.sendto(json.dumps(dict(state,label=msg.decode(),monotonic_ns=time.monotonic_ns())).encode(),peer)
  else:
   data,peer=sink.recvfrom(65536)
   if len(data)<12:state['sink_other']+=1;continue
   pt=data[1]&127
   name={0:'sink_pcmu',8:'sink_pcma',101:'sink_dtmf'}.get(pt,'sink_other');state[name]+=1
   # Expected PCMA D5 -> PCM +8 -> PCMU FE, validated separately by PHP codec API.
   if pt==0 and data[12:]==bytes([0xfe])*160:state['sink_pcmu_valid']+=1
print(json.dumps(dict(state,final=True)),flush=True)

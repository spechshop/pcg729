#!/usr/bin/env python3
import pathlib,json,re,collections
P=pathlib.Path(__file__).resolve().parent
groups={
 'native_parser_inclusive':[r'zif_psampler_rtp_parse',r'rtp_parse_view',r'rtp_uint',r'rtp_byte'],
 'buffer_bridge_inclusive':[r'psampler_binary_with_view',r'buffer_read_acquire',r'buffer_read_release',r'binary_slice_view',r'zif_psampler_binary_slice',r'psampler_swoole'],
 'udp_receive_inclusive':[r'recvfromInto',r'Socket::recvfrom',r'__sys_recvfrom',r'__x64_sys_recvfrom',r'udp_recvmsg',r'__skb_recv_udp',r'__skb_try_recv_datagram'],
 'reactor_epoll_scheduler_inclusive':[r'epoll',r'ep_poll',r'ep_send_events',r'ep_deliver_event',r'schedule',r'swoole::Reactor',r'swoole::PHPCoroutine::(yield|resume)',r'swoole::Coroutine::(yield|resume)',r'jump_fcontext',r'make_fcontext'],
 'zend_property_inclusive':[r'zend_update_property',r'zend_std_write_property',r'zend_get_property_offset'],
 'allocation_copy_inclusive':[r'emalloc',r'efree',r'zend_mm_',r'__libc_malloc',r'__libc_free',r'memcpy',r'memmove',r'zend_string_alloc'],
 'object_lifecycle_inclusive':[r'zend_objects_new',r'zend_objects_store_del',r'zend_object_std_dtor'],
 'perf_observer_inclusive':[r'perf_event',r'perf_iterate',r'__perf',r'perf_ctx',r'intel_pmu',r'x86_pmu'],
 'unpack_inclusive':[r'zif_unpack',r'php_unpack'],
 'codec_inclusive':[r'decodePcma',r'encodePcm',r'pcma_to',r'pcm_to',r'ulaw',r'alaw']
}
output={}
for d in sorted(P.glob('profile-*')):
 f=d/'script.txt'
 if not f.is_file():continue
 counts=collections.Counter();leaves=collections.Counter();n=0;kernel=unknown=0
 for block in f.read_text().split('\n\n'):
  if 'cpu_core/cycles/:' not in block:continue
  frames=[x.strip() for x in block.splitlines() if re.match(r'^\s+[0-9a-f]+\s',x)]
  if not frames:continue
  n+=1
  leaf=re.sub(r'^[0-9a-f]+\s+','',frames[0]);leaves[leaf]+=1
  kernel+=('[kernel.kallsyms]' in frames[0])
  unknown+=('[unknown]' in frames[0])
  full='\n'.join(frames)
  for g,patterns in groups.items():
   if any(re.search(p,full) for p in patterns):counts[g]+=1
 output[d.name]=dict(samples=n,kernel_leaf=kernel,unknown_leaf=unknown,
  groups={g:dict(samples=counts[g],percent=100*counts[g]/n) for g in groups},
  top_leaf=[dict(symbol=k,samples=v,percent=v*100/n) for k,v in leaves.most_common(25)])
(P/'profile-summary.json').write_text(json.dumps(output,indent=2))
print(json.dumps(output,indent=2))

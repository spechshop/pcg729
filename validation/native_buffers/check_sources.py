from pathlib import Path
import hashlib,json,subprocess
out=Path(__file__).resolve().parent;root=out.parents[1]
ps=Path('/home/lotus/projetos/psampler');src=root/'source/php-src/ext/psampler'
rows=[]
for name in ['psampler_binary.h','psampler_native.h','binary_buffer.c','byte_buffer.c','pcm_buffer.c','pcm_core.c','pcm_analyzer.c']:
 a=(ps/name).read_bytes();b=(src/name).read_bytes()
 rows.append({'file':name,'identical':a==b,'psampler_sha256':hashlib.sha256(a).hexdigest(),'build_source_sha256':hashlib.sha256(b).hexdigest()})
(out/'results/source-hashes.json').write_text(json.dumps(rows,indent=2)+'\n')
print(json.dumps(rows))

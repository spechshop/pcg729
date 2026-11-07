"""Independent signal measurements, standard library only; does not read analyzer output."""
import array, math, cmath, json, csv, statistics
from pathlib import Path
D=Path(__file__).parent
x=array.array('h'); x.frombytes((D/'audio.s16le').read_bytes())
def db(v): return 20*math.log10(max(v,1e-12)/32768)
def rms(v):
    m=statistics.mean(v)
    return math.sqrt(sum((s-m)**2 for s in v)/len(v))
def fft(a):
    a=list(map(complex,a)); n=len(a); j=0
    for i in range(1,n):
        bit=n>>1
        while j&bit: j^=bit; bit>>=1
        j^=bit
        if i<j: a[i],a[j]=a[j],a[i]
    size=2
    while size<=n:
        root=cmath.exp(-2j*math.pi/size)
        for start in range(0,n,size):
            w=1
            for k in range(start,start+size//2):
                u=a[k]; v=a[k+size//2]*w
                a[k]=u+v; a[k+size//2]=u-v; w*=root
        size*=2
    return a
def spectrum(start,end):
    a=x[round(start*8):round(end*8)]; n=len(a)
    hann=[v*(.5-.5*math.cos(2*math.pi*i/(n-1))) for i,v in enumerate(a)]
    size=1
    while size<n*4: size*=2
    z=fft(hann+[0]*(size-n)); powers=[abs(v)**2 for v in z[:size//2]]
    k=max(range(5,len(powers)),key=powers.__getitem__)
    p,q,r=[math.log(max(v,1e-30)) for v in powers[k-1:k+2]]
    f=(k+.5*(p-r)/(p-2*q+r))*8000/size
    # Continuous-frequency least-squares sinusoid, independent of Goertzel bins.
    mean=statistics.mean(a)
    co=[math.cos(2*math.pi*f*i/8000) for i in range(n)]
    si=[math.sin(2*math.pi*f*i/8000) for i in range(n)]
    cc=sum(v*v for v in co); ss=sum(v*v for v in si); cs=sum(c*s for c,s in zip(co,si))
    yc=sum((v-mean)*c for v,c in zip(a,co)); ys=sum((v-mean)*s for v,s in zip(a,si))
    det=cc*ss-cs*cs; ac=(yc*ss-ys*cs)/det; ass=(ys*cc-yc*cs)/det
    energy=sum((v-mean)**2 for v in a)
    explained=(ac*yc+ass*ys)/energy
    return dict(start_ms=start,end_ms=end,peak_hz=f,rms_dbfs=db(rms(a)),sine_energy_fraction=explained,peak_sample=max(abs(v) for v in a))
def runs(flags,step):
    out=[]; start=None
    for i,v in enumerate(flags+[False]):
        if v and start is None: start=i
        elif not v and start is not None:
            out.append([start*step,i*step]); start=None
    return out
levels=[]
for i in range(0,len(x),8): levels.append(db(rms(x[i:i+8])))
with (D/'reference-rms-1ms.csv').open('w') as f:
    w=csv.writer(f); w.writerow(['start_ms','ac_rms_dbfs']); w.writerows(enumerate(levels))
regions={}
for th in [-50,-45,-42,-35]:
    rr=runs([v>th for v in levels],1)
    # Join gaps <=30ms for independent activity envelopes; not a speech classifier.
    joined=[]
    for a,b in rr:
        if joined and a-joined[-1][1]<=30: joined[-1][1]=b
        else: joined.append([a,b])
    regions[str(th)]=[r for r in joined if r[1]-r[0]>=20]
spectra=[spectrum(a,b) for a,b in [(200,1000),(5200,6000),(10200,11000),(15200,16000),(20200,21000),(25200,26000),(30140,30300),(33360,33460),(37240,37400),(31400,31600),(45000,45200)]]
out=dict(sample_min=min(x),sample_max=max(x),clipped_samples=sum(abs(v)>=32767 for v in x),global_rms_dbfs=db(rms(x)),activity_regions_ms=regions,spectra=spectra)
(D/'reference.json').write_text(json.dumps(out,indent=2))
print(json.dumps(out,indent=2))

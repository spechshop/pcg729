#define _POSIX_C_SOURCE 200809L
#include <arpa/inet.h>
#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <sys/resource.h>
#include <sys/socket.h>
#include <time.h>
#include <unistd.h>
static double now(void) {struct timespec t;clock_gettime(CLOCK_MONOTONIC,&t);return t.tv_sec+t.tv_nsec/1e9;}
int main(int argc,char **argv) {
 if(argc!=5) return 2;
 int port=atoi(argv[1]),size=atoi(argv[2]),cpu=atoi(argv[3]),pps=atoi(argv[4]);(void)cpu;
 if(size<1||size>2048)return 2;
 int fd=socket(AF_INET,SOCK_DGRAM,0); if(fd<0)return 3;
 struct sockaddr_in addr={.sin_family=AF_INET,.sin_port=htons(port)};
 inet_pton(AF_INET,"127.0.0.1",&addr.sin_addr);
 unsigned char payload[2048]; for(int i=0;i<size;++i)payload[i]=(unsigned char)(i*37+11);
 if(size==172) {const unsigned char rtp[12]={0x80,0x00,0x12,0x34,0x00,0x00,0x00,0xa0,0x01,0x02,0x03,0x04};memcpy(payload,rtp,12);}
 setvbuf(stdin,NULL,_IONBF,0);setvbuf(stdout,NULL,_IONBF,0);
 unsigned command;long sent=0,errors=0;struct rusage r0;getrusage(RUSAGE_SELF,&r0);double cpu0=r0.ru_utime.tv_sec+r0.ru_utime.tv_usec/1e6+r0.ru_stime.tv_sec+r0.ru_stime.tv_usec/1e6;double t0=now(),deadline=t0;
 while(fread(&command,sizeof command,1,stdin)==1 && command) {
  if(command==UINT32_MAX) {getrusage(RUSAGE_SELF,&r0);cpu0=r0.ru_utime.tv_sec+r0.ru_utime.tv_usec/1e6+r0.ru_stime.tv_sec+r0.ru_stime.tv_usec/1e6;t0=now();deadline=t0;sent=errors=0;continue;}
  for(unsigned i=0;i<command;++i) {
   if(pps) {deadline+=1.0/pps;struct timespec ts={(time_t)deadline,(long)((deadline-(time_t)deadline)*1e9)};while(clock_nanosleep(CLOCK_MONOTONIC,TIMER_ABSTIME,&ts,NULL)==EINTR){} }
   ssize_t n=sendto(fd,payload,(size_t)size,0,(struct sockaddr*)&addr,sizeof addr);
   if(n==size)++sent;else ++errors;
  }
 }
 struct rusage r;getrusage(RUSAGE_SELF,&r);
 double cpu_s=r.ru_utime.tv_sec+r.ru_utime.tv_usec/1e6+r.ru_stime.tv_sec+r.ru_stime.tv_usec/1e6;
 printf("{\"sent\":%ld,\"send_errors\":%ld,\"sender_cpu_s\":%.9f,\"sender_wall_s\":%.9f}\n",sent,errors,cpu_s-cpu0,now()-t0);
 close(fd);return errors?4:0;
}

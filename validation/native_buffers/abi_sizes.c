#include "php.h"
#include "ext/swoole/swoole_buffer_c_api.h"
#include "ext/psampler/psampler_binary.h"
#include "ext/psampler/psampler_native.h"
#include <stdio.h>
int main(void){printf("swoole_api=%zu swoole_view=%zu binary_provider=%zu binary_view=%zu ZTS=%d\n",sizeof(swoole_buffer_read_api_v1),sizeof(swoole_buffer_read_view_v1),sizeof(psampler_binary_provider),sizeof(psampler_binary_view),ZTS);return 0;}

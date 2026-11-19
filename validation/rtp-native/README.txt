Experimento RTP nativo local

Executar na raiz:
  buildroot/bin/php mc-native.php
Em outro terminal, usar o lsend.php existente como antes.
O receptor exige a API e a bridge; não permite fallback. Mantém PCMA PT 8,
telephone-event PT 101, 127.0.0.1:40000, peer 127.0.0.1:50000 e
setReceiveOnlyMode(false). Não houve medição de ganho de desempenho.

API experimental:
  psampler_rtp_parse(object $source, int $packetLength, object $target,
                     bool $strict = true): ?string

Source é um produtor registrado na infraestrutura binária do psampler:
Swoole Buffer pela bridge, ou ByteBuffer (inclusive dois spans circulares).
packetLength é a contagem válida retornada pelo produtor, limitada ao tamanho
atual do buffer; valores negativos ou além do tamanho sempre geram ValueError.

Target deve ser um objeto recém-criado com as propriedades públicas graváveis:
version, padding, extension, cc, marker, payloadType, sequence, timestamp,
ssrc (int), payloadRaw e rawPacket (string). stdClass também é aceito.
O parser preenche os campos diretamente, sem array/objeto intermediário por
pacote. Retorna uma string própria com o cabeçalho completo (CSRC e extensão
incluídos); strlen(retorno) é o offset do payload. strlen(target->payloadRaw)
é o comprimento do payload. Não modifica rawPacket em parsing bem-sucedido.
O conteúdo da extensão é opaco; seu comprimento em palavras é validado.

strict=true exige versão 2, limites válidos e padding não zero que caiba no
payload; remove padding do payload e lança ValueError para RTP inválido.
Erros de parsing estrito ocorrem antes de modificar target. Erros de setters
podem deixar target parcialmente preenchido, como atribuições PHP normais.

strict=false é o modo de compatibilidade usado exclusivamente pela fábrica
rtpc::fromNativeBuffer: aceita versão/padding como o construtor tradicional,
inclusive padding no payloadRaw. Para cabeçalho truncado <12 mantém defaults;
para CSRC/extensão truncados mantém os campos fixos e rawPacket integral,
com payload vazio. Nestes casos retorna null. Não é um filtro RTP estrito.

A view nativa é liberada antes de escrever propriedades (setters podem lançar
exceções ou executar PHP). Só strings próprias sobrevivem à chamada. rtpc
compartilha a string de payload por refcount com seu snapshot; rawPacket
continua público, tipado e materializado na primeira leitura. O construtor
tradicional e as demais funções do psampler não foram alterados.

Fontes alterados:
  source/php-src/ext/psampler/binary_buffer.c e binary_buffer.h
  source/php-src/ext/psampler/psampler.c e psampler.stub.php
  source/php-src/ext/psampler/tests/rtp_parse.phpt
Os mesmos arquivos foram sincronizados em downloads/psampler para evitar
perder a implementação caso o SPC reutilize a cópia local de download.
  libspech/plugins/Utils/sip/rtpc.php
  libspech/plugins/Utils/sip/MediaChannel.php
  libspech/test_rtpc_native.php
  mc-native.php (novo)
  validation/rtp-native/{functional.php,media.php} e evidências nesta pasta.

Build executado com sucesso:
  bin/spc build --build-cli "bcg729,psampler,opus,swoole,sockets,filter,standard" --enable-zts --no-strip
Saída: buildroot/bin/php, PHP 8.5.12 ZTS, estático, debug_info, not stripped.
Extensões verificadas: bcg729 1.0.3, psampler 0.7.0, opus 1.1.1,
Swoole 6.3.0RC1, sockets, filter e standard. nm confirma
zif_psampler_rtp_parse e rtp_parse_view. Nenhum php-config do sistema usado.
source.sha256 registra fontes locais equivalentes no build e downloads;
artifacts.sha256 registra binário e originais preservados.

Testes funcionais executados no binário final:
  buildroot/bin/php libspech/test_rtpc_native.php
    PASS: 773 casos; propriedades, referências, clone, serialização,
    reconstrução, rawPacket tardio; mais 31 divisões em buffer circular.
  buildroot/bin/php validation/rtp-native/functional.php
    PASS: 517 casos Swoole UDP versus construtor tradicional; limites,
    versão, padding, CSRC, extensão, binário e reutilização do buffer.
  buildroot/bin/php validation/rtp-native/media.php
    PASS: UDP real, caminho nativo comprovado pelo snapshot tardio,
    peers, callbacks, PCMA->PCMU, DTMF RFC 4733 e encaminhamento.
  4 PHPT: rtp_parse, binary_slice, binary_slice_signature, binary_slice_swoole
    PASS: 4/4, incluindo exceção em setter e liberação do empréstimo.
    Resultado completo: phpt.txt.
  mc-native.php com três pacotes PCMA e um evento DTMF: PASS.
    Saída: receiver-smoke.txt. Processo encerrado após o teste.

Teste adicional de regressão:
  (cd libspech && ../buildroot/bin/php test_media_channel_member_ptime.php)
    FAIL no cleanup, linha 485: socket simulado não chama o construtor do
    Swoole; MediaChannel::close chama Socket->cancel e recebe
    "must call constructor first". O fixture declara __construct vazio.
    O trecho close não foi alterado. Não foi corrigido fora do escopo.
    Teste MediaChannel com sockets reais passou.

mc.php e lsend.php preservados byte a byte. Alterações preexistentes preservadas.
Sem commits, pushes, publicações, perf ou carga. Nenhum outro binário foi
sobrescrito fora do build do projeto. O build usou os fontes locais alterados.

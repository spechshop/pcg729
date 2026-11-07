from pathlib import Path
p=Path('/home/lotus/projetos/psampler/psampler.c')
s=p.read_text()
needle='    php_info_print_table_row(2, "PCMAnalyzer",\n'
insert='''    php_info_print_table_row(2, "AcousticStreamAnalyzer",
        "__construct(int sampleRate=8000, int frameDurationMs=20), "
        "on(string event, callable callback): AcousticStreamAnalyzer, "
        "push(string pcm): void, state(): array, reset(): void");
'''
assert needle in s and 'php_info_print_table_row(2, "AcousticStreamAnalyzer"' not in s
p.write_text(s.replace(needle,insert+needle,1))

<?php
// diagnostica temporanea del server
header('Content-Type: text/plain; charset=utf-8');
$fn = ['shell_exec','exec','proc_open','popen','system','passthru'];
echo "PHP: ".PHP_VERSION."\n";
echo "disable_functions: ".(ini_get('disable_functions') ?: '(nessuna)')."\n\n";
foreach ($fn as $f) printf("%-12s %s\n", $f, function_exists($f) ? 'disponibile' : 'DISABILITATA');
echo "\nupload_max_filesize: ".ini_get('upload_max_filesize');
echo "\npost_max_size:       ".ini_get('post_max_size');
echo "\nmax_execution_time:  ".ini_get('max_execution_time');
echo "\nmemory_limit:        ".ini_get('memory_limit');
echo "\nmax_input_time:      ".ini_get('max_input_time');
$s = __DIR__.'/storage';
echo "\n\nstorage scrivibile:  ".(is_writable($s)?'sì':'NO');
echo "\nspazio libero:       ".round(@disk_free_space($s)/1073741824,1)." GB";
echo "\ntmp di PHP:          ".(sys_get_temp_dir());
echo "\nstesso filesystem:   ".((@stat(sys_get_temp_dir())['dev'] ?? 1) === (@stat($s)['dev'] ?? 2) ? 'sì' : 'no (rename fallisce, serve copy)');

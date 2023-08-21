<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;
use File;

class TestZipArchive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-zip-archive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $zip = new ZipArchive();
        $base_path = public_path()."/subidos";
        $fileName = $base_path.'/test_global.zip';
        $input_dir = $base_path.'/temp';

        $this->info('Inicia');

        if (!$zip->open($fileName, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            exit("Error abriendo ZIP en $fileName");
        }
        
        $files = File::files($input_dir);

        foreach ($files as $key => $value) {
            $relativeNameInZipFile = basename($value);
            $zip->addFile($value, $relativeNameInZipFile);
        }
         
        // $options = ['remove_all_path' => TRUE];
        // $zip->addGlob("*.png", GLOB_BRACE, $options);
        $zip->close();
        
        $this->info('Termina');
        
        if (File::exists($input_dir)) {
            File::deleteDirectory($input_dir);
        }

        $this->info('Eimina contenido');
    }
}


// /var/www/html/api/apivales/public/var/www/html/api/apivales/public/subidos/temp
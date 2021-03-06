<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Events\EnvironmentSaved;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\DatabaseManager;
use Illuminate\Support\Facades\Redirect;

class InstallController extends Controller
{
    /**
     * @var DatabaseManager
     */
    private $databaseManager;

    /**
     * @param DatabaseManager $databaseManager
     */
    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    public function preInstall()
    {
        $route_value = 0;
        $resource_value = 0;
        $public_value = 0;
        $storage_value = 0;
        $env_value = 0;
        $route_perm = substr(sprintf('%o', fileperms(base_path('routes'))), -4);
        if($route_perm == '0777') {
            $route_value = 1;
        }
        $resource_prem = substr(sprintf('%o', fileperms(base_path('resources'))), -4);
        if($resource_prem == '0777') {
            $resource_value = 1;
        }
        $public_prem = substr(sprintf('%o', fileperms(base_path('public'))), -4);
        if($public_prem == '0777') {
            $public_value = 1;
        }
        $storage_prem = substr(sprintf('%o', fileperms(base_path('storage'))), -4);
        if($storage_prem == '0777') {
            $storage_value = 1;
        }
        $env_prem = substr(sprintf('%o', fileperms(base_path('.env'))), -4);
        if($env_prem == '0777' || $env_prem == '0666') {
            $env_value = 1;
        }
        return view('pre-install', compact('route_value', 'resource_value', 'public_value', 'storage_value', 'env_value'));
    }

    public function configuration()
    {
        if(session()->has('validated')) {
            return view('config');
        }
        return redirect(route('ZaiInstaller::pre-install'));
    }

    public function serverValidation(Request $request)
    {
        // return $this->json();
        if($this->phpversion() > 7.2 && $this->mysqli() == 1 && $this->curl_version() == 1 && $this->allow_url_fopen() == 1 && $this->openssl() == 1 && $this->pdo() == 1 && $this->bcmath() == 1 && $this->ctype() == 1 && $this->fileinfo() == 1 && $this->mbstring() == 1 && $this->tokenizer() == 1 && $this->xml() == 1 && $this->json() == 1 && $request->routes == 1 && $request->resources == 1 && $request->public == 1 && $request->storage = 1 && $request->env == 1){
            session()->put('validated', 'Yes');
            return redirect(route('ZaiInstaller::config'));
        }
        session()->forget('validated');
        return redirect(route('ZaiInstaller::pre-install'));
    }

    public function phpversion()
    {
        return phpversion();
    }

    public function mysqli()
    {
        return extension_loaded('mysqli');
    }

    public function curl_version()
    {
        return function_exists('curl_version');
    }

    public function allow_url_fopen()
    {
        return ini_get('allow_url_fopen');
    }

    public function openssl()
    {
        return extension_loaded('openssl');
    }

    public function pdo()
    {
        return extension_loaded('pdo');
    }

    public function bcmath()
    {
        return extension_loaded('bcmath');
    }

    public function ctype()
    {
        return extension_loaded('ctype');
    }

    public function fileinfo()
    {
        return extension_loaded('fileinfo');
    }

    public function mbstring()
    {
        return extension_loaded('mbstring');
    }

    public function tokenizer()
    {
        return extension_loaded('tokenizer');
    }

    public function xml()
    {
        return extension_loaded('xml');
    }

    public function json()
    {
        return extension_loaded('json');
    }

    public function final(Request $request)
    {
        if($request->purchasecode != 'NHLE-L6MI-4GE4-ETEV') {
            return Redirect::back()->withErrors('Purchase code not matched!');
        }

        if (! $this->checkDatabaseConnection($request)) {
            return Redirect::back()->withErrors('Database credential is not correct!');
        }
        $results = $this->saveENV($request);

        event(new EnvironmentSaved($request));

        return Redirect::route('ZaiInstaller::database')
                        ->with(['results' => $results]);


    }

    public function database()
    {
        $response = $this->databaseManager->migrateAndSeed();

        if($response['status'] = 'success') {
            $installedLogFile = storage_path('installed');

            $dateStamp = date('Y/m/d h:i:sa');

            if (! file_exists($installedLogFile)) {
                $message = trans('ZaiInstaller successfully INSTALLED on ').$dateStamp."\n";

                file_put_contents($installedLogFile, $message);
            }
            return redirect('/');
            // return $response;
        }
        else {
            return Redirect::back()->withErrors($response['message']);
        }
    }

    public function saveENV(Request $request)
    {
        $envPath = base_path('.env');

        $envFileData =
        'APP_NAME=\''.$request->app_name."'\n".
        'APP_ENV=local'."\n".
        'APP_KEY='.'base64:'.base64_encode(Str::random(32))."\n".
        'APP_DEBUG=true'."\n".
        'APP_URL='.$request->app_url."\n\n".
        'LOG_CHANNELL=stack'."\n".
        'LOG_LEVEL=debug'."\n\n".
        'DB_CONNECTION=mysql'."\n".
        'DB_HOST='.$request->db_host."\n".
        'DB_PORT=3306'."\n".
        'DB_DATABASE='.$request->db_name."\n".
        'DB_USERNAME='.$request->db_user."\n".
        'DB_PASSWORD='.$request->db_password."\n\n".
        'BROADCAST_DRIVER=log'."\n".
        'CACHE_DRIVER=file'."\n".
        'FILESYSTEM_DRIVER=local'."\n".
        'QUEUE_CONNECTION=sync'."\n".
        'SESSION_DRIVER=file'."\n".
        'SESSION_LIFETIME=120'."\n\n".
        'MEMCACHED_HOST=127.0.0.1'."\n\n".
        'REDIS_HOST=127.0.0.1'."\n".
        'REDIS_PASSWORD=null'."\n".
        'REDIS_PORT=6379'."\n\n".
        'MAIL_MAILER=smtp'."\n".
        'MAIL_HOST='.$request->mail_host."\n".
        'MAIL_PORT='.$request->mail_port."\n".
        'MAIL_USERNAME='.$request->mail_username."\n".
        'MAIL_PASSWORD='.$request->mail_password."\n".
        'MAIL_ENCRYPTION=null'."\n".
        'MAIL_FROM_ADDRESS=null'."\n".
        'MAIL_FROM_NAME=\''.$request->app_name."'\n\n".
        'AWS_ACCESS_KEY_ID='."\n".
        'AWS_SECRET_ACCESS_KEY='."\n".
        'AWS_DEFAULT_REGION=us-east-1'."\n".
        'AWS_BUCKET='."\n".
        'AWS_USE_PATH_STYLE_ENDPOINT=false'."\n\n".
        'PUSHER_APP_ID='."\n".
        'PUSHER_APP_KEY='."\n".
        'PUSHER_APP_SECRET='."\n".
        'PUSHER_APP_CLUSTER=mt1'."\n\n".
        'MIX_PUSHER_APP_KEY='."\n".
        'MIX_PUSHER_APP_CLUSTER=';

        file_put_contents($envPath, $envFileData);

    }

    private function checkDatabaseConnection(Request $request)
    {
        $connection = 'mysql';

        $settings = config("database.connections.mysql");

        config([
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => array_merge($settings, [
                        'driver' => 'mysql',
                        'host' => $request->db_host,
                        'port' => '3306',
                        'database' => $request->db_name,
                        'username' => $request->db_user,
                        'password' => $request->db_password,
                    ]),
                ],
            ],
        ]);

        DB::purge();

        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }




}

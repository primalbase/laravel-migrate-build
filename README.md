# laravel-migrate-build

### composer
<pre>
composer require primalbase/laravel-migrate-build
</pre>

### app.php
<pre>
  'providers' => array(
    'Primalbase\Migrate\MigrateServiceProvider',
  );
</pre>


### publish config file
<pre>
$ php artisan config:publish primalbase/laravel-migrate-build
</pre>

### config/packages/primalbase/laravel-migrate-build/config.php
<pre>
    'client_id' => GoogleClientId

    'client_email' => GoogleClientEmail

    'client_key_path' => GoogleClientKeyPath(*.p12)

    'client_key_password' => GoogleClientKeyPassword(notasecret)

    'spread_sheet_name' => GoogleSpreadsheet SheetName,

    // Sheet availability check
    'available_sheet_check' => [
      'col' => 1,
      'row' => 1,
      'value' => 'テーブル定義書',
    ],
</pre>


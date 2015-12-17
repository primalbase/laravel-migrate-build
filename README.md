# laravel-migrate-build

### supported only laravel 4.* 

### 0.0.9 support only *.json

*.p12 use 0.0.8.0 branch

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
    'client_key_path' => GoogleClientKeyPath(*.json)

    'spread_sheet_name' => GoogleSpreadsheet SheetName,

    // Sheet availability check
    // Default A1 = 'テーブル定義書'
    'available_sheet_check' => [
      'col' => 1,
      'row' => 1,
      'value' => 'テーブル定義書',
    ],
</pre>


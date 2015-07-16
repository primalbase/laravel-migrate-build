<?php namespace Primalbase\Migrate;

use Illuminate\Support\ServiceProvider;

class MigrateServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('primalbase/migrate');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind('primalbase::command.migrate.build', function($app) {
			return new MigrateBuild();
		});
		$this->commands(array(
			'primalbase::command.migrate.build'
		));
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}

<?php

namespace empleadoEstatalBot;

use empleadoEstatalBot\RedditManager\RedditManager;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';


$console = new Application();
$console->register('get:start')
    ->setDescription('Start the Get worker.')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $empleado = new empleadoEstatal();
        $output->writeln($empleado->get());
    });

$console->register('post:start')
    ->addOption('dry-run',
        'd',
        InputOption::VALUE_OPTIONAL,
        'Dry run, do not post anything.',
        'n')
    ->setDescription('Start the Post worker.')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $empleado = new empleadoEstatal();
        $output->writeln($empleado->post());
    });

$console->register('config:seed')
    ->setDescription('Seed the db.')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $empleado = new empleadoEstatal();
        $output->writeln($empleado->seed());
    });
$console->run();

class empleadoEstatal
{
    public static $log;
    public $db;
    protected $config;

    public function __construct()
    {
        self::$log = new Logger('ChePibe');
        self::$log->pushHandler(new StreamHandler('tmp/empleadoEstatalBot.log'));

        try {
            $this->config = Yaml::parse(file_get_contents('config/config.yml'));
        } catch (\Exception $e) {
            self::$log->addCritical('Missing or wrong config: ' . $e->getMessage());
            throw $e;
        }

        try {
            $capsule = new Capsule;

            $capsule->addConnection([
                'driver' => 'mysql',
                'host' => $this->config['database']['host'],
                'database' => $this->config['database']['name'],
                'username' => $this->config['database']['user'],
                'password' => $this->config['database']['pass'],
                'charset' => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix' => '',
            ]);

            $capsule->setAsGlobal();
            $capsule->bootEloquent();

        } catch (\Exception $e) {
            self::$log->addCritical('Cannot connect to database: ' . $e->getMessage());
            throw $e;
        }

        if ($this->config['bot']['debug']) {
            $this->config['bot']['subreddits'] = 'empleadoEstatalBot';
        }
    }

    public function get()
    {
        $reddit = new RedditManager($this->config['reddit']);
        $reddit->login();
        $reddit->getNewPosts();
        $reddit->savePosts();
    }


    public function seed()
    {
        Capsule::schema()->create('posts', function ($table) {
            $table->increments('id');
            $table->string('subreddit');
            $table->string('thing')->unique();
            $table->string('url');
            $table->tinyInteger('status');
            $table->tinyInteger('tries')->unsigned();
            $table->string('info');
            $table->timestamps();
        });

        return 'Done.';
    }
}

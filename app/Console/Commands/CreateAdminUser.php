<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bloom:admin {--create} {--install}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->option('create'))
        {
            print "CREATION PART";
            $this->createAdmin();
        } elseif ($this->option('install'))
        {
            print "INSTALATIONPART";
        } else {
            $this->error("No option provided. Options: (--create - creates an admin user; --install - installs admind dashboard).");
        }

        return 0;
    }

    protected function createAdmin()
    {
        $email = $this->ask('Enter the admin email:');
        $password = $this->secret('Enter the admin password:');

        print $email;
        print $password;

        // Create an admin user
//        $adminUser = new User();
//        $adminUser->email = $email;
//        $adminUser->password = Hash::make($password);
//        $adminUser->is_admin = true;

//        $adminUser->save();

        $this->info('Admin user created successfully.');
    }
}

<?php

namespace Hypnobox\Migration\Command;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;


abstract class AbstractMigrateCommand extends Command
{
    protected function configure()
    {
        $this->addOption('--config', '-c', InputArgument::OPTIONAL, 'config file path', 'migrations/config.yml');
    }
    
    /**
     * @return Connection[]
     */
    protected function getConnections(array $configs)
    {
        $connections = array();

        foreach ($configs['databases'] as $databaseConfig) {
            $databaseConfig['charset'] = 'utf8';
            
            $config        = new Configuration();
            
            try {
                $connection = DriverManager::getConnection($databaseConfig, $config);
                $connection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
            } catch (Exception $exception) {
                echo $exception->getMessage() . "\n";
                continue;
            }
            
            $connections[] = $connection;
        }
        
        return $connections;
    }
}

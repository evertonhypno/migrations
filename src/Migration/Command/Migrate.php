<?php

namespace Migration\Command;

use DirectoryIterator;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;

class Migrate extends Command
{

    protected function configure()
    {
        $this->setName('migrations:migrate');
    }

    /**
     * @return Connection[]
     */
    private function getConnections(array $configs)
    {
        $connections = array();

        foreach ($configs['databases'] as $databaseConfig) {
            $config        = new Configuration();
            $connections[] = DriverManager::getConnection($config, $databaseConfig);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ymlParser = new Parser();
        $config    = $ymlParser->parse('migrations/config.yml');

        $directoryIterator = new DirectoryIterator($config['path']);

        $versionTable = $config['version_table'];
        
        foreach ($this->getConnections($config) as $connection) {
            if (!$connection->getSchemaManager()->tablesExist($versionTable)) {
                $schema = $connection->getSchemaManager()->createSchema();
                $table  = $schema->createTable($versionTable);
                
                $table->addColumn('id', 'integer', array('autoincrement'));
                $table->addColumn('name', 'string', array('notnull', 'customSchemaOptions' => array('unique')));
                $table->addColumn('status', 'integer');
                $table->addColumn('error', 'string');
                
                $queries = $schema->toSql($connection->getDatabasePlatform());
                
                foreach ($queries as $query) {
                    $connection->exec($query);
                }
            }
            
            foreach ($directoryIterator as $classFile) {
                require_once $classFile->getPathname();

                $className = $classFile->getBasename('php');

                $migration = new $className();
                
                $executed = (bool) $connection->createQueryBuilder()
                    ->select(array('count(1)'))
                    ->where('name = :name')
                    ->setParameter('name', $className)
                    ->setMaxResults(1)
                    ->execute()
                    ->fetchColumn();
                
                if (!$executed) {
                    try {
                        $connection->exec($migration->getUpSql());
                    } catch (Exception $exception) {
                        $connection->createQueryBuilder()
                            ->insert($versionTable)
                            ->values(array(
                                'name' => $className,
                                'status' => 2,
                                'error' => $exception->getMessage(),
                            ));
                        continue;
                    }
                    
                    $connection->createQueryBuilder()
                        ->insert($versionTable)
                        ->values(array(
                            'name' => $className,
                            'status' => 1,
                        ));
                }
            }
        }
    }

}

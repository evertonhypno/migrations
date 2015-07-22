<?php

namespace Hypnobox\Migration\Command;

use DirectoryIterator;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\SchemaDiff;
use Exception;
use RegexIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;
use Zend\Code\Reflection\FileReflection;

class MigrateCommand extends Command
{

    protected function configure()
    {
        $this->setName('migrations:migrate')
            ->setDescription('run the migrations based on the configs');
        $this->addOption('--config', '-c', InputArgument::OPTIONAL, 'config file path', 'migrations/config.yml');
    }

    /**
     * @return Connection[]
     */
    private function getConnections(array $configs)
    {
        $connections = array();

        foreach ($configs['databases'] as $databaseConfig) {
            $config        = new Configuration();
            try {
                $connection = DriverManager::getConnection($databaseConfig, $config);
                $connection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
            } catch (\Exception $exception) {
                echo $exception->getMessage() . "\n";
                continue;
            }
            
            $connections[] = $connection;
        }
        
        return $connections;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ymlParser = new Parser();
        $config    = $ymlParser->parse(file_get_contents($input->getOption('config')));

        $directoryIterator = new RegexIterator(new DirectoryIterator($config['path']), '/.php$/');

        $versionTable = $config['version_table'];
        
        foreach ($this->getConnections($config) as $connection) {
            if (!$connection->getSchemaManager()->tablesExist($versionTable)) {
                $schema = $connection->getSchemaManager()->createSchema();
                $table  = $schema->createTable($versionTable);
                
                $table->addColumn('id', 'integer', array('autoincrement'));
                $table->addColumn('name', 'string', array('notnull', 'customSchemaOptions' => array('unique')));
                $table->addColumn('status', 'integer');
                $table->addColumn('error', 'string');
                
                $diff = new SchemaDiff(array($table));
                
                $queries = $diff->toSql($connection->getDatabasePlatform());
                
                foreach ($queries as $query) {
                    $connection->exec($query);
                }
            }
            
            foreach ($directoryIterator as $classFile) {
                if ($classFile->isDot()) {
                    continue;
                }
                
                $fileName = $classFile->getPathname();
                require_once $fileName;

                $reflection = new FileReflection($classFile->getPathname());
                
                $migration = $reflection->getClass()->newInstance();
                
                $executed = (bool) $connection->createQueryBuilder()
                    ->select(array('count(1)'))
                    ->from($versionTable)
                    ->where('name = :name')
                    ->setParameter('name', $fileName)
                    ->setMaxResults(1)
                    ->execute()
                    ->fetchColumn();
                
                if (!$executed) {
                    $output->writeln("<info>executing migration $fileName</info>");
                    try {
                        $connection->exec($migration->getUpSql());
                        $output->writeln("<info>$fileName executed succesfuly!</info>");
                    } catch (Exception $exception) {
                        $connection->createQueryBuilder()
                            ->insert($versionTable)
                            ->values(array(
                                'name' => ":name",
                                'status' => 2,
                                'error' => ":error",
                            ))
                            ->setParameter('error', $exception->getMessage())
                            ->setParameter('name', $fileName)
                            ->execute();
                        $output->writeln("<error>executing migration $fileName</error>");
                        $output->writeln("<error>{$exception->getMessage()}</error>");
                        continue;
                    }
                    
                    $connection->createQueryBuilder()
                        ->insert($versionTable)
                        ->values(array(
                            'name' => ":name",
                            'status' => 1,
                        ))
                        ->setParameter('name', $fileName)
                        ->execute();
                }
            }
        }
    }

}

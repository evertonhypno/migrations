<?php

namespace Hypnobox\Migration\Command;

use DirectoryIterator;
use Exception;
use Hypnobox\Iterator\ReverseDirectoryIterator;
use RegexIterator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;
use Zend\Code\Reflection\FileReflection;

class DownCommand extends AbstractMigrateCommand
{
    protected function configure()
    {
        $this->setName('migrations:down')
            ->setDescription('revert migrations')
            ->addArgument('version', InputArgument::REQUIRED, 'version to go down to');
        
        parent::configure();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ymlParser    = new Parser();
        $config       = $ymlParser->parse(file_get_contents($input->getOption('config')));
        $version      = $input->getArgument('version');
        $versionTable = $config['version_table'];
        
        $directoryIterator = new RegexIterator(
            new ReverseDirectoryIterator(
                new DirectoryIterator($config['path']), 
                '/.php$/'
            )
        );
        
        foreach ($this->getConnections($config) as $connection) {
            foreach ($directoryIterator as $file) {
                if ($file->isDot() || $file->isDir()) {
                    continue;
                }

                list($migrationVersion, ) = explode('_', $file->getBasename(), 1);

                if ($version < $migrationVersion) {
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
                    ->andWhere('status = 1')
                    ->setParameter('name', $fileName)
                    ->setMaxResults(1)
                    ->execute()
                    ->fetchColumn();
                
                if ($executed) {
                    $output->writeln("<info> executing migration down for $fileName </info>");
                    
                    try {
                        $connection->exec($migration->getDownSql());
                        $output->writeln("<info> $fileName down executed succesfuly! </info>");
                    } catch (Exception $exception) {
                        $output->writeln("<error> error executing migration down for $fileName</error>");
                        $output->writeln("<error> {$exception->getMessage()} </error>");
                        continue;
                    }
                    
                    $connection->createQueryBuilder()
                        ->delete($versionTable)
                        ->where('name = :name')
                        ->setParameter('name', $fileName)
                        ->execute();
                }
            }
        }
        
    }
}

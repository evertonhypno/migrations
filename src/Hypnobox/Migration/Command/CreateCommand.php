<?php

namespace Hypnobox\Migration\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;
use Zend\Code\Generator\ClassGenerator;

class CreateCommand extends Command
{
    protected function configure()
    {
        $this->setName('migrations:create')
            ->addOption('--name', '-n', InputOption::VALUE_REQUIRED, 'the migration name')
            ->addOption('--config', '-c', InputArgument::OPTIONAL, 'config file path', 'migrations/config.yml');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ymlParser = new Parser();
        $config    = $ymlParser->parse(file_get_contents($input->getOption('config')));
        $path      = $config['path'];
        $name      = $input->getOption('name');
        $classGen  = new ClassGenerator();
        
        $content = $classGen->addMethod('getUpSql')
            ->addMethod('getDownSql')
            ->setName($name)
            ->generate();
        
        file_put_contents(sprintf('%s/%s_%s', $path, date_timestamp_get(date_create(), $name), $content));
    }
    
    public function getTemplate()
    {
        return <<<TEMPLATE
class %s {
    public function getUpSql()
    {
        return "";
    }

    public function getDownSql()
    {
        return "";
    }
}
TEMPLATE;
    }
}

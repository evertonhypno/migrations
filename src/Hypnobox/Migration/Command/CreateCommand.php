<?php

namespace Hypnobox\Migration\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\MethodGenerator;

class CreateCommand extends Command
{
    protected function configure()
    {
        $this->setName('migrations:create')
            ->addArgument('name', InputArgument::REQUIRED, 'the migration name')
            ->addOption('--config', '-c', InputOption::VALUE_OPTIONAL, 'config file path', 'migrations/config.yml');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ymlParser = new Parser();
        $config    = $ymlParser->parse(file_get_contents($input->getOption('config')));
        $path      = $config['path'];
        $name      = $input->getArgument('name');
        $classGen  = new ClassGenerator();
        
        $content = $classGen->setName($name)
            ->addMethod(
                'getUpSql',
                array(),
                MethodGenerator::FLAG_PUBLIC,
                'return \'\';'
            )
            ->addMethod(
                'getDownSql',
                array(),
                MethodGenerator::FLAG_PUBLIC,
                'return \'\';'
            )
            ->addMethod('preUp')
            ->addMethod('postUp')
            ->addMethod('preDown')
            ->addMethod('postDown')
            
            ->generate();
        
        file_put_contents(
            sprintf('%s/%s_%s.php', $path, date('YmdHis'), $name), 
            sprintf("<?php\n%s",$content)
        );
    }
}

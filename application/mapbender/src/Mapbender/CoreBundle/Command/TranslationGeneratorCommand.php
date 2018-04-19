<?php

namespace Mapbender\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class TranslationGenerator
 *
 * @package   Mapbender\CoreBundle\Command
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 * @copyright 2014 by WhereGroup GmbH & Co. KG
 */
class TranslationGeneratorCommand extends ContainerAwareCommand {

    protected function configure()
    {
        $this->setDefinition(array())
            ->setHelp("Updates translations files of the bundle")
            ->setName('mapbender:generate:translation')
            ->setDescription('Generates a Mapbender translation');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var SplFileInfo $dir */

        $finder      = new Finder();
        $application = $this->getApplication();
        foreach ($finder->in('mapbender/src/Mapbender/*/Resources/translations/')->name('*.xlf') as $dir) {
            preg_match("/^([^.]+)[.]([^.]+)[.]/", $dir->getBasename(), $matches);
            $paths  = preg_split("|/|", $dir->getPath());
            $bundle = $paths[2] . $paths[3];
            list($r, $domain, $locale) = $matches;
            var_dump($domain);
            $cmd = "app/console translation:update --output-format=xlf --force $locale $bundle";
            $output->writeln("<comment>$cmd</comment>");
            echo `$cmd`;
//            $this->runCommand('translation:update',
//                array('locale'          => $locale,
//                      'bundle'          => $bundle,
//                      '--output-format' => 'xlf',
//                      '--force'         => true)
//            ,$output);
        }
    }

    protected function runCommand($command, array $options, $output)
    {
        $this->getApplication()->run(new ArrayInput( array_merge(array('command' => $command),$options)),$output);
    }
}


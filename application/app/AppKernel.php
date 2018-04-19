<?php

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // Standard Symfony2 bundles
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Symfony\Bundle\AsseticBundle\AsseticBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new JMS\AopBundle\JMSAopBundle(),
            new JMS\DiExtraBundle\JMSDiExtraBundle($this),
            new JMS\SecurityExtraBundle\JMSSecurityExtraBundle(),

            // Extra bundles required by Mapbender3/OWSProxy3
            new FOS\JsRoutingBundle\FOSJsRoutingBundle(),

            // FoM bundles
            new FOM\CoreBundle\FOMCoreBundle(),
            new FOM\ManagerBundle\FOMManagerBundle(),
            new FOM\UserBundle\FOMUserBundle(),

            // Mapbender3 bundles
            new Mapbender\CoreBundle\MapbenderCoreBundle(),
            new Mapbender\WmcBundle\MapbenderWmcBundle(),
            new Mapbender\WmsBundle\MapbenderWmsBundle(),
            new Mapbender\ManagerBundle\MapbenderManagerBundle(),
            new Mapbender\PrintBundle\MapbenderPrintBundle(),
            new Mapbender\DigitizerBundle\MapbenderDigitizerBundle(),
            new Mapbender\MobileBundle\MapbenderMobileBundle(),
            new Mapbender\LdapIntegrationBundle\MapbenderLdapIntegrationBundle(),

            // OWSProxy3 bundles
            new OwsProxy3\CoreBundle\OwsProxy3CoreBundle(),
            new Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle(),
            
            new Mapbender\AlkisBundle\MapbenderAlkisBundle(),
            new Mapbender\WorkflowBundle\MapbenderWorkflowBundle(),
            new IMAG\LdapBundle\IMAGLdapBundle(),
            new Mapbender\WmtsBundle\MapbenderWmtsBundle(),
            new Mapbender\HroBundle\MapbenderHroBundle(),

            new \Mapbender\MapToolBundle\MapbenderMapToolBundle()
        );

        // dev and ALL test environments get some extra sugar...
        $isDevKernel = false;
        if ('dev' == $this->getEnvironment() || strpos($this->getEnvironment(), 'test') == 0) {
            $isDevKernel = true;
        }

        if ($isDevKernel) {
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.yml');
    }
}

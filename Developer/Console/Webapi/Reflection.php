<?php
declare(strict_types=1);

namespace Betagento\Developer\Console\Webapi;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class Reflection extends Command
{
    const API_ID = 'api';
    const HTTP_METHOD = 'http_method';

   
    public function __construct (
        protected \Betagento\Developer\Webapi\Reflection $webApiReflection
    )
    {
        parent::__construct();
    }
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $options = [
            new InputOption(
				self::API_ID,
				'-a',
				InputOption::VALUE_REQUIRED,
				'API ID : -a : /V1/carts/:cartId/shipping-information '
            ),
            new InputOption(
				self::HTTP_METHOD,
				'-m',
				InputOption::VALUE_OPTIONAL,
				'HTTP Method: -m POST '
			)
        ];
        $this->setName('beta_dev:api_reflection');
        $this->setDescription('Refection API');
        $this->setDefinition($options);
        parent::configure();
    }
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return null|int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if(!($serviceId = $input->getOption(self::API_ID)))
        {
            $output->writeln(sprintf("Please provide a service id/url, example: %s", "/V1/carts/:cartId/shipping-information"));
            return Command::FAILURE;
        }
        if(!($httpMethod = $input->getOption(self::HTTP_METHOD)))
        {
            $output->writeln(sprintf("Please provide a HTTP METHOD, example: %s", "-m POST|GET|PUT"));
            return Command::FAILURE;
        }
        $meta = $this->webApiReflection->show($serviceId, $httpMethod);
       
        if (!$meta) {
            $output->writeln(sprintf("<error>Can not find any information for given service!</error>"));
            return Command::FAILURE;
        }

        $serviceClassName = $meta['route']['service_class'];
        $serviceMethodName = $meta['route']['service_method'];
        $instanceType = $meta['route']['preference_class'];
        $outputStyle = new OutputFormatterStyle(null, null, ['bold', 'underscore']);
        $output->getFormatter()->setStyle('fire', $outputStyle);
        $output->writeln("<fire>General Information</>");

        $serviceInformationTable = new Table($output);
        $serviceInformationTable->addRow(['Service Class', $serviceClassName])->addRow(new TableSeparator());
        $serviceInformationTable->addRow(['Preference Class', $instanceType])->addRow(new TableSeparator());
        $serviceInformationTable->addRow(['Method Name', $serviceMethodName])->addRow(new TableSeparator());
        $serviceInformationTable->addRow(['HTTP METHOD', $httpMethod]);
        $serviceInformationTable->render();

        $inputParamsTable = new Table($output);

        array_walk($meta['input'], 
            function($item) use ($inputParamsTable) {
                $inputParamsTable->addRow([$item['name'],$item['type']])->addRow(new TableSeparator());
            }
        );

        $outputStyle = new OutputFormatterStyle(null, null, ['bold', 'underscore']);
        $output->getFormatter()->setStyle('fire', $outputStyle);
        $output->writeln("<fire>Input Parammetters</>");

        $inputParamsTable->render();
        $outputStyle = new OutputFormatterStyle(null, null, ['bold', 'underscore']);
        $output->getFormatter()->setStyle('fire', $outputStyle);
        $output->writeln("<fire>Output</>");

        $outputTable = new Table($output);
        $outputTable->addRow([$meta['output']['type']])->render();
        return Command::SUCCESS;
    }    
}

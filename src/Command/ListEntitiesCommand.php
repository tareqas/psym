<?php

namespace TareqAS\Psym\Command;

use Psy\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TareqAS\Psym\Helper;

class ListEntitiesCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('lse')
            ->setDescription('Lists all entities managed by Doctrine')
            ->addArgument('entityName', InputArgument::OPTIONAL, 'Name of the entity', '')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Search for entities or a specific entity\'s properties' , '')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $formatter = $output->getFormatter();
        $entityName = $input->getArgument('entityName');
        $searchTerm = $input->getOption('search');
        $entities = Helper::getEntities();
        $prints = [];

        if ($entityName && !($entityName = Helper::findEntityName($entityName))) {
            $io->error("Entity '{$input->getArgument('entityName')}' not found");

            return 1;
        }

        if ($entityName && $searchTerm) {
            $properties = Helper::getProperties($entityName);
            $matches = $this->searchProperties($properties, $searchTerm);
            $io->text("$entityName: \n");
            foreach ($matches as $key => $value) {
                $formatedKey = $this->highlightMatches($key, $searchTerm, $formatter);
                $formatedValue = $this->highlightMatches($value, $searchTerm, $formatter);
                $prints[] = "$formatedKey => $formatedValue";
            }
        } else if ($searchTerm) {
            $matches = $this->searchEntities($entities, $searchTerm);
            foreach ($matches as $entity) {
                $prints[] = $this->highlightMatches($entity, $searchTerm, $formatter);
            }
        } else if ($entityName) {
            $prints[] = $this->highlightMatches($entityName, $searchTerm, $formatter);
        } else {
            $prints = $entities;
        }

        foreach ($prints as $index => $text) {
            $serial = str_pad($index + 1, strlen(count($prints).''), '0', STR_PAD_LEFT);
            $io->text($serial.': '.$text);
        }
        $io->text("\n");

        return 0;
    }

    private function searchEntities($entities, $searchTerm): array
    {
        $entities = array_filter($entities, function($entity) use ($searchTerm) {
            return stripos($entity, $searchTerm) !== false;
        });
        sort($entities);

        return $entities;
    }

    private function searchProperties($properties, $searchTerm): array
    {
        $properties = array_filter($properties, function($value, $key) use ($searchTerm) {
            return stripos($key, $searchTerm) !== false || stripos($value, $searchTerm) !== false;
        }, ARRAY_FILTER_USE_BOTH);

        return $properties;
    }

    private function highlightMatches($subject, $searchTerm, OutputFormatterInterface $formatter): string
    {
        $searchTerm = preg_quote($searchTerm, '/');

        if (preg_match("/($searchTerm)/i", $subject, $matches)) {
            $searchTerm = $matches[1];
        }

        return str_ireplace($searchTerm, $formatter->format("<fg=red>$searchTerm</>"), $subject);
    }
}

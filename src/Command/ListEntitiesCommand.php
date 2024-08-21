<?php

namespace TareqAS\Psym\Command;

use Psy\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TareqAS\Psym\Util\DoctrineEM;

class ListEntitiesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('lse')
            ->setDescription('Lists all entities managed by Doctrine')
            ->addArgument('entityName', InputArgument::OPTIONAL, 'Entity name', '')
            ->addArgument('propertyName', InputArgument::OPTIONAL, 'Property name of the entity', '')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $formatter = $output->getFormatter();

        $entityName = $input->getArgument('entityName');
        $propertyName = $input->getArgument('propertyName');
        $entityFullName = DoctrineEM::findEntityFullName($entityName);

        $entities = DoctrineEM::getEntitiesTables();
        $entityMapping = DoctrineEM::getEntitiesMappings()[$entityFullName] ?? [];
        $rows = [];

        if (!$entityFullName && $entityName) {
            $headers = ['entity', 'table'];
            $matches = $this->searchEntities($entities, $entityName);

            foreach ($matches as $entityMapping => $tableName) {
                $formattedEntity = $this->highlightMatches($entityMapping, $entityName, $formatter);
                $formattedTable = $this->highlightMatches($tableName, $entityName, $formatter);
                $rows[] = [$formattedEntity, $formattedTable];
            }
        } elseif ($entityMapping && $propertyName) {
            $headers = ['property', 'column', 'type', 'default'];
            $matches = $this->searchProperties($entityMapping['properties'], $propertyName);

            foreach ($matches as $property => $map) {
                $formattedProperty = $this->highlightMatches($property, $propertyName, $formatter);
                $formattedColumn = $this->highlightMatches($map['column'], $propertyName, $formatter);
                $rows[] = [$formattedProperty, $formattedColumn, $map['type'], $map['default']];
            }

            $io->text("<fg=black;bg=green>$entityFullName:</>");
        } elseif ($entityMapping) {
            $headers = ['property', 'column', 'type', 'default'];

            foreach ($entityMapping['properties'] as $property => $map) {
                $rows[] = [$property, $map['column'], $map['type'], $map['default']];
            }

            $io->text("<fg=black;bg=green>$entityFullName:</>");
        } else {
            $headers = ['entity', 'table'];

            foreach ($entities as $entityName => $tableName) {
                $rows[] = [$entityName, $tableName];
            }
        }

        $io->table($headers, $rows);

        return 0;
    }

    private function searchEntities(array $entities, string $searchTerm): array
    {
        return array_filter($entities, function ($tableName, $entityName) use ($searchTerm) {
            return false !== stripos($tableName, $searchTerm) || false !== stripos($entityName, $searchTerm);
        }, ARRAY_FILTER_USE_BOTH);
    }

    private function searchProperties(array $properties, string $searchTerm): array
    {
        return array_filter($properties, function ($map, $property) use ($searchTerm) {
            return false !== stripos($property, $searchTerm) || false !== stripos($map['column'], $searchTerm);
        }, ARRAY_FILTER_USE_BOTH);
    }

    private function highlightMatches(string $subject, string $searchTerm, OutputFormatterInterface $formatter): string
    {
        $searchTerm = preg_quote($searchTerm, '/');

        if (preg_match("/($searchTerm)/i", $subject, $matches)) {
            $searchTerm = $matches[1];
        }

        return str_ireplace($searchTerm, $formatter->format("<fg=red>$searchTerm</>"), $subject);
    }
}

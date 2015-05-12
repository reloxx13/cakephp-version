<?php
namespace Josegonzalez\Version\Event\Bake;

use Cake\Datasource\ConnectionManager;
use Cake\Event\Event as CakeEvent;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

class TableEvent extends Event
{
    public function beforeRenderEntity(CakeEvent $event)
    {
        $this->checkAssociation($event, 'versions');
    }

    public function beforeRenderTestCase(CakeEvent $event)
    {
        $name = $event->subject->viewVars['subject'];
        $pattern = '/^' . preg_quote($name) . '_(\w+)_version$/';
        foreach (array_keys($event->subject->viewVars['fixtures']) as $key) {
            if (preg_match($pattern, $key)) {
                unset($event->subject->viewVars['fixtures'][$key]);
            }
        }
    }

    public function beforeRenderTable(CakeEvent $event)
    {
        $this->checkAssociation($event, 'versions');
        $this->fixVersionTables($event);
    }

    protected function fixVersionTables(CakeEvent $event)
    {
        if (!preg_match('/Versions$/', $event->subject->viewVars['name'])) {
            return;
        }

        unset($event->subject->viewVars['rulesChecker']['version_id']);
        foreach ($event->subject->viewVars['associations']['belongsTo'] as $i => $association) {
            if ($association['alias'] === 'Versions' && $association['foreignKey'] === 'version_id') {
                unset($event->subject->viewVars['associations']['belongsTo'][$i]);
            }
        }
    }

    protected function checkAssociation(CakeEvent $event, $tableSuffix)
    {
        $subject = $event->subject;
        $connection = ConnectionManager::get($subject->viewVars['connection']);
        $schema = $connection->schemaCollection();

        $versionTable = sprintf('%s_%s', Hash::get($event->subject->viewVars, 'table'), $tableSuffix);
        if (!in_array($versionTable, $schema->listTables())) {
            return false;
        }

        $event->subject->viewVars['behaviors']['Josegonzalez/Version.Version'] = [
            sprintf("'versionTable' => '%s'", $versionTable),
        ];

        $event->subject->viewVars['associations']['belongsTo'] = $this->modifyBelongsTo($event);
        $event->subject->viewVars['rulesChecker'] = $this->modifyRulesChecker($event);

        return true;
    }

    protected function modifyBelongsTo(CakeEvent $event)
    {
        $belongsTo = $event->subject->viewVars['associations']['belongsTo'];

        foreach ($belongsTo as $i => $association) {
            if ($association['alias'] !== 'Versions' || $association['foreignKey'] !== 'version_id') {
                continue;
            }

            unset($belongsTo[$i]);
        }
        return $belongsTo;
    }

    protected function modifyRulesChecker(CakeEvent $event)
    {
        $rulesChecker = $event->subject->viewVars['rulesChecker'];

        foreach ($rulesChecker as $key => $config) {
            if (Hash::get($config, 'extra') !== 'Versions' || $key !== 'version_id') {
                continue;
            }

            unset($rulesChecker[$key]);
        }

        return $rulesChecker;
    }
}

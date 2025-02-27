<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Problem API implementation.
 */
class CProblem extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	protected $tableName = 'problem';
	protected $tableAlias = 'p';
	protected $sortColumns = ['eventid'];

	public const OUTPUT_FIELDS = ['eventid', 'source', 'object', 'objectid', 'clock', 'ns', 'r_eventid', 'r_clock', 'r_ns',
		'correlationid', 'userid', 'name', 'acknowledged', 'severity', 'cause_eventid', 'opdata', 'suppressed',
		'urls'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options = []) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'eventids' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'groupids' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'hostids' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'objectids' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'source' =>						['type' => API_INT32, 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE]), 'default' => EVENT_SOURCE_TRIGGERS],
			'object' =>						['type' => API_INT32, 'in' => implode(',', [EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE, EVENT_OBJECT_SERVICE]), 'default' => EVENT_OBJECT_TRIGGER],
			'severities' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)), 'uniq' => true, 'default' => null],
			'eventid_from' =>				['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			'eventid_till' =>				['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			'time_from' =>					['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			'time_till' =>					['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			'acknowledged' =>				['type' => API_BOOLEAN, 'flags' => API_ALLOW_NULL, 'default' => null],
			'suppressed' =>					['type' => API_BOOLEAN, 'flags' => API_ALLOW_NULL, 'default' => null],
			'symptom' =>					['type' => API_BOOLEAN, 'flags' => API_ALLOW_NULL, 'default' => null],
			'recent' =>						['type' => API_BOOLEAN, 'flags' => API_ALLOW_NULL, 'default' => null],
			'evaltype' =>					['type' => API_INT32, 'in' => implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), 'default' => TAG_EVAL_TYPE_AND_OR],
			'tags' =>						['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'tag' =>						['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>					['type' => API_INT32, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])],
				'value' =>						['type' => API_STRING_UTF8]
			]],
			'filter' =>						['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['eventid', 'source', 'object', 'objectid', 'r_eventid', 'correlationid', 'userid', 'name', 'acknowledged', 'severity', 'cause_eventid', 'action', 'action_userid']],
			'search' =>						['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
			'searchByAny' =>				['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>				['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>				['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>		['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>						['type' => API_OUTPUT, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>				['type' => API_FLAG, 'default' => false],
			'selectAcknowledges' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['acknowledgeid', 'userid', 'clock', 'message', 'action', 'old_severity', 'new_severity', 'suppress_until', 'taskid']), 'default' => null],
			'selectSuppressionData' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['maintenanceid', 'suppress_until', 'userid']), 'default' => null],
			'selectTags' =>					['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['tag', 'value']), 'default' => null],
			'sortfield' =>					['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>					['type' => API_SORTORDER, 'default' => []],
			'limit' =>						['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>					['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>				['type' => API_BOOLEAN, 'default' => false],
			'nopermissions' =>				['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$res = DBselect($this->createSelectQuery($this->tableName, $options), $options['limit']);

		$db_problems = [];

		while ($row = DBfetch($res)) {
			if ($options['countOutput']) {
				$db_problems = $row['rowscount'];
			}
			else {
				$db_problems[$row['eventid']] = $row;
			}
		}

		if ($options['countOutput']) {
			return $db_problems;
		}

		if ($db_problems) {
			$db_problems = $this->addRelatedObjects($options, $db_problems);
			$db_problems = $this->unsetExtraFields($db_problems, ['eventid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_problems = array_values($db_problems);
			}
		}

		return $db_problems;
	}

	protected function applyQueryFilterOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryFilterOptions($table_name, $table_alias, $options, $sql_parts);

		// source and object
		$sql_parts['where'][] = dbConditionInt('p.source', [$options['source']]);
		$sql_parts['where'][] = dbConditionInt('p.object', [$options['object']]);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$user_groups = getUserGroupsByUserId(self::$userData['userid']);

				// specific triggers
				if ($options['objectids'] !== null) {
					$options['objectids'] = array_keys(API::Trigger()->get([
						'output' => [],
						'triggerids' => $options['objectids'],
						'editable' => $options['editable'],
						'preservekeys' => true
					]));
				}
				// all triggers
				else {
					$sql_parts['where'][] = 'NOT EXISTS ('.
						'SELECT NULL'.
						' FROM functions f,items i,hosts_groups hgg'.
							' LEFT JOIN rights r'.
								' ON r.id=hgg.groupid'.
									' AND '.dbConditionInt('r.groupid', $user_groups).
						' WHERE p.objectid=f.triggerid'.
							' AND f.itemid=i.itemid'.
							' AND i.hostid=hgg.hostid'.
						' GROUP BY i.hostid'.
						' HAVING MAX(permission)<'.($options['editable'] ? PERM_READ_WRITE : PERM_READ).
							' OR MIN(permission) IS NULL'.
							' OR MIN(permission)='.PERM_DENY.
					')';
				}

				if ($options['source'] == EVENT_SOURCE_TRIGGERS) {
					$sql_parts = self::addTagFilterSqlParts($user_groups, $sql_parts);
				}
			}
			elseif ($options['object'] == EVENT_OBJECT_ITEM || $options['object'] == EVENT_OBJECT_LLDRULE) {
				// specific items or lld rules
				if ($options['objectids'] !== null) {
					if ($options['object'] == EVENT_OBJECT_ITEM) {
						$items = API::Item()->get([
							'output' => [],
							'itemids' => $options['objectids'],
							'editable' => $options['editable'],
							'preservekeys' => true
						]);
						$options['objectids'] = array_keys($items);
					}
					elseif ($options['object'] == EVENT_OBJECT_LLDRULE) {
						$items = API::DiscoveryRule()->get([
							'output' => [],
							'itemids' => $options['objectids'],
							'editable' => $options['editable'],
							'preservekeys' => true
						]);
						$options['objectids'] = array_keys($items);
					}
				}
				// all items or lld rules
				else {
					$user_groups = getUserGroupsByUserId(self::$userData['userid']);

					$sql_parts['where'][] = 'EXISTS ('.
						'SELECT NULL'.
						' FROM items i,hosts_groups hgg'.
							' JOIN rights r'.
								' ON r.id=hgg.groupid'.
									' AND '.dbConditionInt('r.groupid', $user_groups).
						' WHERE p.objectid=i.itemid'.
							' AND i.hostid=hgg.hostid'.
						' GROUP BY hgg.hostid'.
						' HAVING MIN(r.permission)>'.PERM_DENY.
							' AND MAX(r.permission)>='.($options['editable'] ? PERM_READ_WRITE : PERM_READ).
					')';
				}
			}
		}

		// eventids
		if ($options['eventids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('p.eventid', $options['eventids']);
		}

		// objectids
		if ($options['objectids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('p.objectid', $options['objectids']);
		}

		// groupids
		if ($options['groupids'] !== null) {
			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sql_parts['from']['f'] = 'functions f';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['from']['hg'] = 'hosts_groups hg';
				$sql_parts['where']['p-f'] = 'p.objectid=f.triggerid';
				$sql_parts['where']['f-i'] = 'f.itemid=i.itemid';
				$sql_parts['where']['i-hg'] = 'i.hostid=hg.hostid';
				$sql_parts['where']['hg'] = dbConditionInt('hg.groupid', $options['groupids']);
			}
			// lld rules and items
			elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['from']['hg'] = 'hosts_groups hg';
				$sql_parts['where']['p-i'] = 'p.objectid=i.itemid';
				$sql_parts['where']['i-hg'] = 'i.hostid=hg.hostid';
				$sql_parts['where']['hg'] = dbConditionInt('hg.groupid', $options['groupids']);
			}
		}

		// hostids
		if ($options['hostids'] !== null) {
			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER) {
				$sql_parts['from']['f'] = 'functions f';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['where']['p-f'] = 'p.objectid=f.triggerid';
				$sql_parts['where']['f-i'] = 'f.itemid=i.itemid';
				$sql_parts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			}
			// lld rules and items
			elseif ($options['object'] == EVENT_OBJECT_LLDRULE || $options['object'] == EVENT_OBJECT_ITEM) {
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['where']['p-i'] = 'p.objectid=i.itemid';
				$sql_parts['where']['i'] = dbConditionInt('i.hostid', $options['hostids']);
			}
		}

		// severities
		if ($options['severities'] !== null) {
			// triggers
			if ($options['object'] == EVENT_OBJECT_TRIGGER || $options['object'] == EVENT_OBJECT_SERVICE) {
				sort($options['severities']);

				if ($options['severities'] != range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)) {
					$sql_parts['where'][] = dbConditionInt('p.severity', $options['severities']);
				}
			}
			// ignore this filter for items and lld rules
		}

		// acknowledged
		if ($options['acknowledged'] !== null) {
			$acknowledged = $options['acknowledged'] ? EVENT_ACKNOWLEDGED : EVENT_NOT_ACKNOWLEDGED;
			$sql_parts['where'][] = 'p.acknowledged='.$acknowledged;
		}

		// suppressed
		if ($options['suppressed'] !== null) {
			$sql_parts['where'][] = (!$options['suppressed'] ? 'NOT ' : '').
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_suppress es'.
					' WHERE es.eventid=p.eventid'.
				')';
		}

		// symptom
		if ($options['symptom'] !== null) {
			$sql_parts['where'][] = 'p.cause_eventid IS '.($options['symptom'] ? 'NOT ' : '').' NULL';
		}

		// tags
		if ($options['tags'] !== null) {
			$sql_parts['where'][] = CApiTagHelper::addWhereCondition($options['tags'], $options['evaltype'], 'p',
				'problem_tag', 'eventid'
			);
		}

		// recent
		if ($options['recent'] !== null && $options['recent']) {
			$ok_events_from = time() - timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::OK_PERIOD));

			$sql_parts['where'][] = '(p.r_eventid IS NULL OR p.r_clock>'.$ok_events_from.')';
		}
		else {
			$sql_parts['where'][] = 'p.r_eventid IS NULL';
		}

		// time_from
		if ($options['time_from'] !== null) {
			$sql_parts['where'][] = 'p.clock>='.zbx_dbstr($options['time_from']);
		}

		// time_till
		if ($options['time_till'] !== null) {
			$sql_parts['where'][] = 'p.clock<='.zbx_dbstr($options['time_till']);
		}

		// eventid_from
		if ($options['eventid_from'] !== null) {
			$sql_parts['where'][] = 'p.eventid>='.zbx_dbstr($options['eventid_from']);
		}

		// eventid_till
		if ($options['eventid_till'] !== null) {
			$sql_parts['where'][] = 'p.eventid<='.zbx_dbstr($options['eventid_till']);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->applyFilters($options, $sql_parts);
		}

		return $sql_parts;
	}

	/**
	 * Add SQL parts related to tag-based permissions.
	 *
	 * @param array $usrgrpids
	 * @param array $sql_parts
	 *
	 * @return array
	 */
	protected static function addTagFilterSqlParts(array $usrgrpids, array $sql_parts): array {
		$tag_filters = CEvent::getTagFilters($usrgrpids);

		if (!$tag_filters) {
			return $sql_parts;
		}

		$sql_parts['from']['f'] = 'functions f';
		$sql_parts['from']['i'] = 'items i';
		$sql_parts['from']['hg'] = 'hosts_groups hg';
		$sql_parts['where']['p-f'] = 'p.objectid=f.triggerid';
		$sql_parts['where']['f-i'] = 'f.itemid=i.itemid';
		$sql_parts['where']['i-hg'] = 'i.hostid=hg.hostid';

		$tag_conditions = [];
		$full_access_groupids = [];

		foreach ($tag_filters as $groupid => $filters) {
			$tags = [];
			$tag_values = [];

			foreach ($filters as $filter) {
				if ($filter['tag'] === '') {
					$full_access_groupids[] = $groupid;

					continue 2;
				}

				if ($filter['value'] === '') {
					$tags[] = $filter['tag'];
				}
				else {
					$tag_values[$filter['tag']][] = $filter['value'];
				}
			}

			$conditions = [];

			if ($tags) {
				$conditions[] = dbConditionString('pt.tag', $tags);
			}
			$parenthesis = $tags || count($tag_values) > 1;

			foreach ($tag_values as $tag => $values) {
				$condition = 'pt.tag='.zbx_dbstr($tag).' AND '.dbConditionString('pt.value', $values);
				$conditions[] = $parenthesis ? '('.$condition.')' : $condition;
			}

			$conditions = count($conditions) > 1 ? '('.implode(' OR ', $conditions).')' : $conditions[0];

			$tag_conditions[] = 'hg.groupid='.zbx_dbstr($groupid).' AND '.$conditions;
		}

		if ($tag_conditions) {
			$sql_parts['from']['pt'] = 'problem_tag pt';
			$sql_parts['where']['p-pt'] = 'p.eventid=pt.eventid';

			if ($full_access_groupids || count($tag_conditions) > 1) {
				foreach ($tag_conditions as &$tag_condition) {
					$tag_condition = '('.$tag_condition.')';
				}
				unset($tag_condition);
			}
		}

		if ($full_access_groupids) {
			$tag_conditions[] = dbConditionInt('hg.groupid', $full_access_groupids);
		}

		$sql_parts['where'][] = count($tag_conditions) > 1
			? '('.implode(' OR ', $tag_conditions).')'
			: $tag_conditions[0];

		return $sql_parts;
	}

	/**
	 * Apply filter conditions to SQL built query.
	 *
	 * @param array $options
	 * @param array $sql_parts
	 *
	 * $options = [
	 *     'filter' => [
	 *         'action' =>          (int)       Acknowledge action(s) that must be performed on filtered problems.
	 *         'action_userid' =>   (int)       User which has performed acknowledge action.
	 *     ]
	 * ]
	 *
	 * @return array
	 */
	protected function applyFilters(array $options, array &$sql_parts): void {
		// Acknowledge action filter properties.
		$acknowledge_actions = [
			'ack.eventid=p.eventid'
		];

		if (array_key_exists('action', $options['filter']) && ctype_xdigit((string) $options['filter']['action'])
				&& $options['filter']['action'] != ZBX_PROBLEM_UPDATE_NONE) {
			$acknowledge_actions[] = 'ack.action & '.$options['filter']['action'].'='.$options['filter']['action'];
		}

		if (array_key_exists('action_userid', $options['filter'])
				&& zbx_ctype_digit($options['filter']['action_userid'])) {
			$acknowledge_actions[] = dbConditionId('ack.userid', [$options['filter']['action_userid']]);
		}

		if (count($acknowledge_actions) > 1) {
			$sql_parts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM acknowledges ack'.
				' WHERE '.implode(' AND ', $acknowledge_actions).
			')';
		}
	}

	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		$this->addRelatedAcknowledges($options, $result);
		$this->addRelatedOpdata($options, $result);
		self::addRelatedSuppressionData($options, $result);
		$this->addRelatedSuppressed($options, $result);
		self::addRelatedTags($options, $result);
		$this->addRelatedUrls($options, $result);

		return $result;
	}

	private function addRelatedAcknowledges(array $options, array &$result): void {
		if ($options['selectAcknowledges'] === null) {
			return;
		}

		if ($options['selectAcknowledges'] != API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['acknowledges'] = [];
			}
			unset($row);

			$output = $options['selectAcknowledges'] === API_OUTPUT_EXTEND
				? ['acknowledgeid', 'userid', 'eventid', 'clock', 'message', 'action', 'old_severity', 'new_severity',
					'suppress_until', 'taskid'
				]
				: array_unique(array_merge(['acknowledgeid', 'eventid'], $options['selectAcknowledges']));

			$sql_options = [
				'output' => $output,
				'filter' => ['eventid' => array_keys($result)],
				'sortfield' => ['clock'],
				'sortorder' => [ZBX_SORT_DOWN]
			];
			$db_acknowledges = DBselect(DB::makeSql('acknowledges', $sql_options));

			while ($db_acknowledge = DBfetch($db_acknowledges)) {
				$eventid = $db_acknowledge['eventid'];

				if (!in_array('acknowledgeid', $output)) {
					unset($db_acknowledge['acknowledgeid']);
				}

				unset($db_acknowledge['eventid']);

				$result[$eventid]['acknowledges'][] = $db_acknowledge;
			}
		}
		else {
			$db_acknowledges = DBFetchArrayAssoc(DBselect(
				'SELECT a.eventid,COUNT(a.acknowledgeid) AS rowscount'.
				' FROM acknowledges a'.
				' WHERE '.dbConditionInt('a.eventid', array_keys($result)).
				' GROUP BY a.eventid'
			), 'eventid');

			foreach ($result as $eventid => $event) {
				$result[$eventid]['acknowledges'] = array_key_exists($eventid, $db_acknowledges)
					? $db_acknowledges[$eventid]['rowscount']
					: '0';
			}
		}
	}

	private function addRelatedOpdata(array $options, array &$result): void {
		if (!$this->outputIsRequested('opdata', $options['output'])) {
			return;
		}

		$problems = DBFetchArrayAssoc(DBselect(
			'SELECT p.eventid,p.clock,p.ns,t.triggerid,t.expression,t.opdata'.
			' FROM problem p'.
				' JOIN triggers t'.
					' ON t.triggerid=p.objectid'.
			' WHERE '.dbConditionInt('p.eventid', array_keys($result))
		), 'eventid');

		foreach ($result as $eventid => $problem) {
			$result[$eventid]['opdata'] = array_key_exists($eventid, $problems) && $problems[$eventid]['opdata'] !== ''
				? CMacrosResolverHelper::resolveTriggerOpdata($problems[$eventid], ['events' => true])
				: '';
		}
	}

	private static function addRelatedSuppressionData(array $options, array &$result): void {
		if ($options['selectSuppressionData'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['suppression_data'] = [];
		}
		unset($row);

		$output = $options['selectSuppressionData'] === API_OUTPUT_EXTEND
			? ['event_suppressid', 'eventid', 'maintenanceid', 'suppress_until', 'userid']
			: array_unique(array_merge(['event_suppressid', 'eventid'], $options['selectSuppressionData']));

		$sql_options = [
			'output' => $output,
			'filter' => ['eventid' => array_keys($result)]
		];
		$db_event_suppress = DBselect(DB::makeSql('event_suppress', $sql_options));

		while ($db_suppression_data = DBfetch($db_event_suppress)) {
			$eventid = $db_suppression_data['eventid'];

			unset($db_suppression_data['event_suppressid'], $db_suppression_data['eventid']);

			$result[$eventid]['suppression_data'][] = $db_suppression_data;
		}
	}

	private function addRelatedSuppressed(array $options, array &$result): void {
		if (!$this->outputIsRequested('suppressed', $options['output'])) {
			return;
		}

		if ($options['selectSuppressionData'] !== null) {
			foreach ($result as &$row) {
				$row['suppressed'] = $row['suppression_data']
					? (string) ZBX_PROBLEM_SUPPRESSED_TRUE
					: (string) ZBX_PROBLEM_SUPPRESSED_FALSE;
			}
			unset($row);
		}
		else {
			foreach ($result as &$row) {
				$row['suppressed'] = (string) ZBX_PROBLEM_SUPPRESSED_FALSE;
			}
			unset($row);

			$sql_options = [
				'output' => ['eventid'],
				'filter' => ['eventid' => array_keys($result)]
			];
			$db_event_suppress = DBselect(DB::makeSql('event_suppress', $sql_options));

			while ($db_suppression_data = DBfetch($db_event_suppress)) {
				$result[$db_suppression_data['eventid']]['suppressed'] = (string) ZBX_PROBLEM_SUPPRESSED_TRUE;
			}
		}
	}

	private static function addRelatedTags(array $options, array &$result): void {
		if ($options['selectTags'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['tags'] = [];
		}
		unset($row);

		$output = $options['selectTags'] === API_OUTPUT_EXTEND
			? ['problemtagid', 'eventid', 'tag', 'value']
			: array_unique(array_merge(['problemtagid', 'eventid'], $options['selectTags']));

		$sql_options = [
			'output' => $output,
			'filter' => ['eventid' => array_keys($result)]
		];
		$db_tags = DBselect(DB::makeSql('problem_tag', $sql_options));

		foreach ($result as &$event) {
			$event['tags'] = [];
		}
		unset($event);

		while ($db_tag = DBfetch($db_tags)) {
			$eventid = $db_tag['eventid'];

			unset($db_tag['problemtagid'], $db_tag['eventid']);

			$result[$eventid]['tags'][] = $db_tag;
		}
	}

	private function addRelatedUrls(array $options, array &$result): void {
		if (!$this->outputIsRequested('urls', $options['output'])) {
			return;
		}

		$sql_options = [
			'output' => ['eventid', 'tag', 'value'],
			'filter' => ['eventid' => array_keys($result)]
		];
		$db_tags = DBselect(DB::makeSql('problem_tag', $sql_options));

		$events = [];

		foreach ($result as $event) {
			$events[$event['eventid']]['tags'] = [];
		}

		while ($db_tag = DBfetch($db_tags)) {
			$events[$db_tag['eventid']]['tags'][] = [
				'tag' => $db_tag['tag'],
				'value' => $db_tag['value']
			];
		}

		$urls = DB::select('media_type', [
			'output' => ['event_menu_url', 'event_menu_name'],
			'filter' => [
				'type' => MEDIA_TYPE_WEBHOOK,
				'status' => MEDIA_TYPE_STATUS_ACTIVE,
				'show_event_menu' => ZBX_EVENT_MENU_SHOW
			]
		]);

		$events = CMacrosResolverHelper::resolveMediaTypeUrls($events, $urls);

		foreach ($events as $eventid => $event) {
			$result[$eventid]['urls'] = $event['urls'];
		}
	}
}

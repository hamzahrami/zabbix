<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class containing methods for operations media types.
 */
class CMediatype extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'media_type';
	protected $tableAlias = 'mt';
	protected $sortColumns = ['mediatypeid'];

	/**
	 * Get Media types data
	 *
	 * @param array $options
	 * @param array $options['mediatypeids'] filter by Mediatype IDs
	 * @param boolean $options['type'] filter by Mediatype type [ USER_TYPE_ZABBIX_USER: 1, USER_TYPE_ZABBIX_ADMIN: 2, USER_TYPE_SUPER_ADMIN: 3 ]
	 * @param boolean $options['output'] output only Mediatype IDs if not set.
	 * @param boolean $options['count'] output only count of objects in result. ( result returned in property 'rowscount' )
	 * @param string $options['pattern'] filter by Host name containing only give pattern
	 * @param int $options['limit'] output will be limited to given number
	 * @param string $options['sortfield'] output will be sorted by given property [ 'mediatypeid' ]
	 * @param string $options['sortorder'] output will be sorted in given order [ 'ASC', 'DESC' ]
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['media_type' => 'mt.mediatypeid'],
			'from'		=> ['media_type' => 'media_type mt'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'mediatypeids'				=> null,
			'mediaids'					=> null,
			'userids'					=> null,
			'editable'					=> false,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectMessageTemplates'	=> null,
			'selectUsers'				=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// permission check
		if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
		}
		elseif (!$options['editable'] && self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
		}
		elseif ($options['editable'] || self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			return [];
		}

		// mediatypeids
		if (!is_null($options['mediatypeids'])) {
			zbx_value2array($options['mediatypeids']);
			$sqlParts['where'][] = dbConditionInt('mt.mediatypeid', $options['mediatypeids']);
		}

		// mediaids
		if (!is_null($options['mediaids'])) {
			zbx_value2array($options['mediaids']);

			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.mediaid', $options['mediaids']);
			$sqlParts['where']['mmt'] = 'm.mediatypeid=mt.mediatypeid';
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);

			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.userid', $options['userids']);
			$sqlParts['where']['mmt'] = 'm.mediatypeid=mt.mediatypeid';
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('media_type mt', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('media_type mt', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($mediatype = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $mediatype;
				}
				else {
					$result = $mediatype['rowscount'];
				}
			}
			else {
				$result[$mediatype['mediatypeid']] = $mediatype;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * @param array $mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function create(array $mediatypes): array {
		self::validateCreate($mediatypes);

		$mediatypeids = DB::insert('media_type', $mediatypes);

		foreach ($mediatypes as $index => &$mediatype) {
			$mediatype['mediatypeid'] = $mediatypeids[$index];
		}
		unset($mediatype);

		self::updateParameters($mediatypes, __FUNCTION__);
		self::updateMessageTemplates($mediatypes, __FUNCTION__);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_MEDIA_TYPE, $mediatypes);

		return ['mediatypeids' => $mediatypeids];
	}

	/**
	 * @static
	 *
	 * @param array $mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateCreate(array &$mediatypes): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'type' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [MEDIA_TYPE_EMAIL, MEDIA_TYPE_EXEC, MEDIA_TYPE_SMS, MEDIA_TYPE_WEBHOOK])],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'name')],
			'smtp_server' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'smtp_server')],
			'smtp_helo' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'smtp_helo')],
			'smtp_email' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'smtp_email')],
			'exec_path' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'exec_path')],
			'gsm_modem' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'gsm_modem')],
			'username' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'username')],
			'passwd' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'passwd')],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED])],
			'smtp_port' =>				['type' => API_INT32, 'in' => ZBX_MIN_PORT_NUMBER.':'.ZBX_MAX_PORT_NUMBER],
			'smtp_security' =>			['type' => API_INT32, 'in' => implode(',', [SMTP_CONNECTION_SECURITY_NONE, SMTP_CONNECTION_SECURITY_STARTTLS, SMTP_CONNECTION_SECURITY_SSL_TLS])],
			'smtp_verify_peer' =>		['type' => API_INT32, 'in' => '0,1'],
			'smtp_verify_host' =>		['type' => API_INT32, 'in' => '0,1'],
			'smtp_authentication' =>	['type' => API_INT32, 'in' => implode(',', [SMTP_AUTHENTICATION_NONE, SMTP_AUTHENTICATION_NORMAL])],
			'exec_params' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'exec_params')],
			'maxsessions' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [MEDIA_TYPE_EMAIL, MEDIA_TYPE_EXEC, MEDIA_TYPE_WEBHOOK])], 'type' => API_INT32, 'in' => '0:100'],
											['if' => ['field' => 'type', 'in' => MEDIA_TYPE_SMS], 'type' => API_INT32, 'in' => '1:1']
			]],
			'maxattempts' =>			['type' => API_INT32, 'in' => '1:100'],
			'attempt_interval' =>		['type' => API_TIME_UNIT, 'in' => '0:'.SEC_PER_HOUR],
			'content_type' =>			['type' => API_INT32, 'in' => implode(',', [SMTP_MESSAGE_FORMAT_PLAIN_TEXT, SMTP_MESSAGE_FORMAT_HTML])],
			'script' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'script')],
			'timeout' =>				['type' => API_TIME_UNIT, 'in' => '1:'.SEC_PER_MIN],
			'process_tags' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MEDIA_TYPE_TAGS_DISABLED, ZBX_MEDIA_TYPE_TAGS_ENABLED])],
			'show_event_menu' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_EVENT_MENU_HIDE, ZBX_EVENT_MENU_SHOW])],
			'event_menu_url' =>			['type' => API_URL, 'flags' => API_ALLOW_EVENT_TAGS_MACRO, 'length' => DB::getFieldLength('media_type', 'event_menu_url')],
			'event_menu_name' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'event_menu_name')],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'description')],
			'parameters' =>				['type' => API_OBJECTS, 'fields' => [
				'name' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_param', 'name')],
				'value' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_param', 'value')]
			]],
			'message_templates' =>		['type' => API_OBJECTS, 'uniq' => [['eventsource', 'recovery']], 'fields' => [
				'eventsource' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])],
				'recovery' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
												['if' => ['field' => 'eventsource', 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_SERVICE])], 'type' => API_INT32, 'in' => implode(',', [ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_UPDATE_OPERATION])],
												['if' => ['field' => 'eventsource', 'in' => implode(',', [EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION])], 'type' => API_INT32, 'in' => ACTION_OPERATION],
												['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_INTERNAL], 'type' => API_INT32, 'in' => implode(',', [ACTION_OPERATION, ACTION_RECOVERY_OPERATION])]
				]],
				'subject' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'subject')],
				'message' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'message')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $mediatypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($mediatypes, 'create');
		self::checkRequiredFieldsByType($mediatypes, 'create');
	}

	/**
	 * @param array $mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function update(array $mediatypes): array {
		self::validateUpdate($mediatypes, $db_mediatypes);

		$upd_mediatypes = [];

		foreach ($mediatypes as $mediatype) {
			$upd_mediatype = DB::getUpdatedValues('media_type', $mediatype, $db_mediatypes[$mediatype['mediatypeid']]);

			if ($upd_mediatype) {
				$upd_mediatypes[] = [
					'values' => $upd_mediatype,
					'where' => ['mediatypeid' => $mediatype['mediatypeid']]
				];
			}
		}

		if ($upd_mediatypes) {
			DB::update('media_type', $upd_mediatypes);
		}

		self::updateParameters($mediatypes, __FUNCTION__, $db_mediatypes);
		self::updateMessageTemplates($mediatypes, __FUNCTION__, $db_mediatypes);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_MEDIA_TYPE, $mediatypes, $db_mediatypes);

		return ['mediatypeids' => array_column($mediatypes, 'mediatypeid')];
	}

	/**
	 * @static
	 *
	 * @param array      $mediatypes
	 * @param array|null $db_mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateUpdate(array &$mediatypes, ?array &$db_mediatypes): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'mediatypeid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'type' =>					['type' => API_INT32, 'in' => implode(',', [MEDIA_TYPE_EMAIL, MEDIA_TYPE_EXEC, MEDIA_TYPE_SMS, MEDIA_TYPE_WEBHOOK])],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('media_type', 'name')],
			'smtp_server' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'smtp_server')],
			'smtp_helo' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'smtp_helo')],
			'smtp_email' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'smtp_email')],
			'exec_path' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'exec_path')],
			'gsm_modem' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'gsm_modem')],
			'username' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'username')],
			'passwd' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'passwd')],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED])],
			'smtp_port' =>				['type' => API_INT32, 'in' => ZBX_MIN_PORT_NUMBER.':'.ZBX_MAX_PORT_NUMBER],
			'smtp_security' =>			['type' => API_INT32, 'in' => implode(',', [SMTP_CONNECTION_SECURITY_NONE, SMTP_CONNECTION_SECURITY_STARTTLS, SMTP_CONNECTION_SECURITY_SSL_TLS])],
			'smtp_verify_peer' =>		['type' => API_INT32, 'in' => '0,1'],
			'smtp_verify_host' =>		['type' => API_INT32, 'in' => '0,1'],
			'smtp_authentication' =>	['type' => API_INT32, 'in' => implode(',', [SMTP_AUTHENTICATION_NONE, SMTP_AUTHENTICATION_NORMAL])],
			'exec_params' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'exec_params')],
			'maxsessions' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [MEDIA_TYPE_EMAIL, MEDIA_TYPE_EXEC, MEDIA_TYPE_WEBHOOK])], 'type' => API_INT32, 'in' => '0:100'],
											['if' => ['field' => 'type', 'in' => MEDIA_TYPE_SMS], 'type' => API_INT32, 'in' => '1:1']
			]],
			'maxattempts' =>			['type' => API_INT32, 'in' => '1:100'],
			'attempt_interval' =>		['type' => API_TIME_UNIT, 'in' => '0:'.SEC_PER_HOUR],
			'content_type' =>			['type' => API_INT32, 'in' => implode(',', [SMTP_MESSAGE_FORMAT_PLAIN_TEXT, SMTP_MESSAGE_FORMAT_HTML])],
			'script' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'script')],
			'timeout' =>				['type' => API_TIME_UNIT, 'in' => '1:'.SEC_PER_MIN],
			'process_tags' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MEDIA_TYPE_TAGS_DISABLED, ZBX_MEDIA_TYPE_TAGS_ENABLED])],
			'show_event_menu' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_EVENT_MENU_HIDE, ZBX_EVENT_MENU_SHOW])],
			'event_menu_url' =>			['type' => API_URL, 'flags' => API_ALLOW_EVENT_TAGS_MACRO, 'length' => DB::getFieldLength('media_type', 'event_menu_url')],
			'event_menu_name' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'event_menu_name')],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type', 'description')],
			'parameters' =>				['type' => API_OBJECTS, 'fields' => [
				'name' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_param', 'name')],
				'value' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_param', 'value')]
			]],
			'message_templates' =>		['type' => API_OBJECTS, 'uniq' => [['eventsource', 'recovery']], 'fields' => [
				'eventsource' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])],
				'recovery' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
												['if' => ['field' => 'eventsource', 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_SERVICE])], 'type' => API_INT32, 'in' => implode(',', [ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_UPDATE_OPERATION])],
												['if' => ['field' => 'eventsource', 'in' => implode(',', [EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION])], 'type' => API_INT32, 'in' => ACTION_OPERATION],
												['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_INTERNAL], 'type' => API_INT32, 'in' => implode(',', [ACTION_OPERATION, ACTION_RECOVERY_OPERATION])]
				]],
				'subject' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'subject')],
				'message' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'message')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $mediatypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_mediatypes = DB::select('media_type', [
			'output' => ['mediatypeid', 'type', 'name', 'smtp_server', 'smtp_helo', 'smtp_email', 'exec_path',
				'gsm_modem', 'username', 'passwd', 'status', 'smtp_port', 'smtp_security', 'smtp_verify_peer',
				'smtp_verify_host', 'smtp_authentication', 'exec_params', 'maxsessions', 'maxattempts',
				'attempt_interval', 'content_type', 'script', 'timeout', 'process_tags', 'show_event_menu',
				'event_menu_url', 'event_menu_name', 'description'
			],
			'mediatypeids' => array_column($mediatypes, 'mediatypeid'),
			'preservekeys' => true
		]);

		if (count($db_mediatypes) != count($mediatypes)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkDuplicates($mediatypes, 'update', $db_mediatypes);
		self::checkRequiredFieldsByType($mediatypes, 'update', $db_mediatypes);

		self::addAffectedObjects($mediatypes, $db_mediatypes);
	}

	/**
	 * Check for unique media type names.
	 *
	 * @static
	 *
	 * @param array      $mediatypes
	 * @param string     $method
	 * @param array|null $db_mediatypes
	 *
	 * @throws APIException if a media type name is not unique.
	 */
	private static function checkDuplicates(array $mediatypes, string $method, array $db_mediatypes = null): void {
		$names = [];

		foreach ($mediatypes as $mediatype) {
			if (!array_key_exists('name', $mediatype)) {
				continue;
			}

			if ($method === 'create' || $mediatype['name'] !== $db_mediatypes[$mediatype['mediatypeid']]['name']) {
				$names[] = $mediatype['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicate = DBfetch(
			DBselect('SELECT mt.name FROM media_type mt WHERE '.dbConditionString('mt.name', $names), 1)
		);

		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media type "%1$s" already exists.', $duplicate['name']));
		}
	}

	/**
	 * Check required fields by type.
	 *
	 * @static
	 *
	 * @param array      $mediatypes
	 * @param string     $method
	 * @param array|null $db_mediatypes
	 *
	 * @throws APIException
	 */
	private static function checkRequiredFieldsByType(array &$mediatypes, string $method, array $db_mediatypes = null): void {
		if ($db_mediatypes !== null) {
			$default_values = DB::getDefaults('media_type');
			$default_values['parameters'] = [];

			$type_switch_fields = [
				MEDIA_TYPE_EMAIL => [
					'smtp_server', 'smtp_port', 'smtp_helo', 'smtp_email', 'smtp_security', 'smtp_verify_peer',
					'smtp_verify_host', 'smtp_authentication', 'username', 'passwd', 'content_type'
				],
				MEDIA_TYPE_EXEC => [
					'exec_path', 'exec_params'
				],
				MEDIA_TYPE_SMS => [
					'gsm_modem'
				],
				MEDIA_TYPE_WEBHOOK => [
					'script', 'timeout', 'process_tags', 'show_event_menu', 'event_menu_url', 'event_menu_name',
					'parameters'
				]
			];
		}

		foreach ($mediatypes as $i => &$mediatype) {
			if ($db_mediatypes === null) {
				$db_mediatype = null;
				$db_type = null;
				$type = $mediatype['type'];
				$type_changed = false;
			}
			else {
				$db_mediatype = $db_mediatypes[$mediatype['mediatypeid']];
				$type = array_key_exists('type', $mediatype) ? $mediatype['type'] : $db_mediatype['type'];
				$db_type = $db_mediatype['type'];
				$type_changed = $type != $db_type;
			}

			$api_input_rules = self::getExtraValidationRules($type, $method, $db_type);
			$data = array_intersect_key($mediatype, $api_input_rules['fields']);

			if (!CApiInputValidator::validate($api_input_rules, $data, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			$mediatype = $data + $mediatype;

			switch ($type) {
				case MEDIA_TYPE_EMAIL:
					if ($db_mediatypes === null) {
						break;
					}

					if (array_key_exists('smtp_security', $mediatype)
							&& $mediatype['smtp_security'] == SMTP_CONNECTION_SECURITY_NONE) {
						$mediatype += ['smtp_verify_peer' => 0, 'smtp_verify_host' => 0];
					}

					if (array_key_exists('smtp_authentication', $mediatype)
							&& $mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_NONE) {
						$mediatype += ['username' => '', 'passwd' => ''];
					}
					break;

				case MEDIA_TYPE_EXEC:
					if (array_key_exists('exec_params', $mediatype) && $mediatype['exec_params'] !== '') {
						$pos = strrpos($mediatype['exec_params'], "\n");

						if ($pos === false || strlen($mediatype['exec_params']) != $pos + 1) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Script parameters "%1$s" are missing the last new line feed for media type "%2$s".',
								$mediatype['exec_params'],
								$mediatype['name']
							));
						}
					}
					break;

				case MEDIA_TYPE_WEBHOOK:
					if ($db_mediatypes !== null && array_key_exists('show_event_menu', $mediatype)
							&& $mediatype['show_event_menu'] == ZBX_EVENT_MENU_HIDE) {
						$mediatype += ['event_menu_url' => '', 'event_menu_name' => ''];
					}
					break;
			}

			if ($type_changed) {
				$mediatype = array_intersect_key($default_values, array_flip($type_switch_fields[$db_type]))
					+ $mediatype;
			}
		}
		unset($mediatype);
	}

	/**
	 * Get type specific validation rules.
	 *
	 * @static
	 *
	 * @param int      $type
	 * @param string   $method
	 * @param int|null $db_type
	 *
	 * @return array
	 */
	private static function getExtraValidationRules(int $type, string $method, ?int $db_type): array {
		$api_input_rules = ['type' => API_OBJECT];

		switch ($type) {
			case MEDIA_TYPE_EMAIL:
				$api_input_rules['fields'] = [
					'smtp_server' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
					'smtp_helo' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
					'smtp_email' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY]
				];

				if ($method === 'create' || $type != $db_type) {
					foreach ($api_input_rules['fields'] as &$field) {
						$field['flags'] |= API_REQUIRED;
					}
					unset($field);
				}
				break;

			case MEDIA_TYPE_EXEC:
				$api_input_rules['fields'] = [
					'exec_path' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY]
				];

				if ($method === 'create' || $type != $db_type) {
					$api_input_rules['fields']['exec_path']['flags'] |= API_REQUIRED;
				}
				break;

			case MEDIA_TYPE_SMS:
				$api_input_rules['fields'] = [
					'gsm_modem' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY]
				];

				if ($method === 'create' || $type != $db_type) {
					$api_input_rules['fields']['gsm_modem']['flags'] |= API_REQUIRED;
				}
				break;

			case MEDIA_TYPE_WEBHOOK:
				$api_input_rules['fields'] = [
					'script' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
					'show_event_menu' =>	['type' => API_INT32, 'in' => implode(',', [ZBX_EVENT_MENU_HIDE, ZBX_EVENT_MENU_SHOW])],
					'event_menu_url' =>		['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'show_event_menu', 'in' => ZBX_EVENT_MENU_HIDE], 'type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'event_menu_url')],
												['if' => ['field' => 'show_event_menu', 'in' => ZBX_EVENT_MENU_SHOW], 'type' => API_URL, 'flags' => API_ALLOW_EVENT_TAGS_MACRO | API_NOT_EMPTY]
					]],
					'event_menu_name' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'show_event_menu', 'in' => ZBX_EVENT_MENU_HIDE], 'type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'event_menu_name')],
												['if' => ['field' => 'show_event_menu', 'in' => ZBX_EVENT_MENU_SHOW], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY]
					]],
					'parameters' =>			['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
						'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
						'value' =>				['type' => API_STRING_UTF8]
					]]
				];

				if ($method === 'create' || $type != $db_type) {
					$api_input_rules['fields']['script']['flags'] |= API_REQUIRED;
				}
				break;
		}

		$api_input_rules['fields'] += [
			'smtp_server' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'smtp_server')],
			'smtp_helo' =>					['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'smtp_helo')],
			'smtp_email' =>					['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'smtp_email')],
			'exec_path' =>					['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'exec_path')],
			'gsm_modem' =>					['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'gsm_modem')],
			'username' =>					['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'username')],
			'passwd' =>						['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'passwd')],
			'smtp_port' =>					['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_port')],
			'smtp_security' =>				['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_security')],
			'smtp_verify_peer' =>			['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_verify_peer')],
			'smtp_verify_host' =>			['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_verify_host')],
			'smtp_authentication' =>		['type' => API_INT32, 'in' => DB::getDefault('media_type', 'smtp_authentication')],
			'exec_params' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'exec_params')],
			'content_type' =>				['type' => API_INT32, 'in' => DB::getDefault('media_type', 'content_type')],
			'script' =>						['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'script')],
			'timeout' =>					['type' => API_TIME_UNIT, 'in' => DB::getDefault('media_type', 'timeout')],
			'process_tags' =>				['type' => API_INT32, 'in' => DB::getDefault('media_type', 'process_tags')],
			'show_event_menu' =>			['type' => API_INT32, 'in' => DB::getDefault('media_type', 'show_event_menu')],
			'event_menu_url' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'event_menu_url')],
			'event_menu_name' =>			['type' => API_STRING_UTF8, 'in' => DB::getDefault('media_type', 'event_menu_name')]
		];

		return $api_input_rules;
	}

	/**
	 * Update table "media_type_param" and populate mediatype.parameters by "mediatype_paramid" property.
	 *
	 * @static
	 *
	 * @param array      $mediatypes
	 * @param string     $method
	 * @param array|null $db_mediatypes
	 */
	private static function updateParameters(array &$mediatypes, string $method, array $db_mediatypes = null): void {
		$ins_params = [];
		$upd_params = [];
		$del_paramids = [];

		foreach ($mediatypes as &$mediatype) {
			if (!array_key_exists('parameters', $mediatype)) {
				continue;
			}

			$db_params = ($method === 'update') ? $db_mediatypes[$mediatype['mediatypeid']]['parameters'] : [];

			foreach ($mediatype['parameters'] as &$param) {
				$db_param = current(
					array_filter($db_params, static function(array $db_param) use ($param): bool {
						return $param['name'] === $db_param['name'];
					})
				);

				if ($db_param) {
					$param['mediatype_paramid'] = $db_param['mediatype_paramid'];
					unset($db_params[$db_param['mediatype_paramid']]);

					$upd_param = DB::getUpdatedValues('media_type_param', $param, $db_param);

					if ($upd_param) {
						$upd_params[] = [
							'values' => $upd_param,
							'where' => ['mediatype_paramid' => $upd_param['mediatype_paramid']]
						];
					}
				}
				else {
					$ins_params[] = ['mediatypeid' => $mediatype['mediatypeid']] + $param;
				}
			}
			unset($param);

			$del_paramids = array_merge($del_paramids, array_keys($db_params));
		}
		unset($mediatype);

		if ($del_paramids) {
			DB::delete('media_type_param', ['mediatype_paramid' => $del_paramids]);
		}

		if ($upd_params) {
			DB::update('media_type_param', $upd_params);
		}

		if ($ins_params) {
			$paramids = DB::insert('media_type_param', $ins_params);
		}

		foreach ($mediatypes as &$mediatype) {
			if (!array_key_exists('parameters', $mediatype)) {
				continue;
			}

			foreach ($mediatype['parameters'] as &$param) {
				if (!array_key_exists('mediatype_paramid', $param)) {
					$param['mediatype_paramid'] = array_shift($paramids);
				}
			}
			unset($param);
		}
		unset($mediatype);
	}

	/**
	 * Update table "media_type_message" and populate mediatype.message_templates by "mediatype_messageid" property.
	 *
	 * @static
	 *
	 * @param array      $mediatypes
	 * @param string     $method
	 * @param array|null $db_mediatypes
	 */
	private static function updateMessageTemplates(array &$mediatypes, string $method, array $db_mediatypes = null): void {
		$ins_messages = [];
		$upd_messages = [];
		$del_messageids = [];

		foreach ($mediatypes as &$mediatype) {
			if (!array_key_exists('message_templates', $mediatype)) {
				continue;
			}

			$db_messages = ($method === 'update') ? $db_mediatypes[$mediatype['mediatypeid']]['message_templates'] : [];

			foreach ($mediatype['message_templates'] as &$message) {
				$db_message = current(
					array_filter($db_messages, static function(array $db_message) use ($message): bool {
						return $message['eventsource'] == $db_message['eventsource']
							&& $message['recovery'] == $db_message['recovery'];
					})
				);

				if ($db_message) {
					$message['mediatype_messageid'] = $db_message['mediatype_messageid'];
					unset($db_messages[$db_message['mediatype_messageid']]);

					$upd_message = DB::getUpdatedValues('media_type_message', $message, $db_message);

					if ($upd_message) {
						$upd_messages[] = [
							'values' => $upd_message,
							'where' => ['mediatype_messageid' => $db_message['mediatype_messageid']]
						];
					}
				}
				else {
					$ins_messages[] = ['mediatypeid' => $mediatype['mediatypeid']] + $message;
				}
			}
			unset($message);

			$del_messageids = array_merge($del_messageids, array_keys($db_messages));
		}
		unset($mediatype);

		if ($del_messageids) {
			DB::delete('media_type_message', ['mediatype_messageid' => $del_messageids]);
		}

		if ($upd_messages) {
			DB::update('media_type_message', $upd_messages);
		}

		if ($ins_messages) {
			$messageids = DB::insert('media_type_message', $ins_messages);
		}

		foreach ($mediatypes as &$mediatype) {
			if (!array_key_exists('message_templates', $mediatype)) {
				continue;
			}

			foreach ($mediatype['message_templates'] as &$message) {
				if (!array_key_exists('mediatype_messageid', $message)) {
					$message['mediatype_messageid'] = array_shift($messageids);
				}
			}
			unset($message);
		}
		unset($mediatype);
	}

	/**
	 * @param array $mediatypeids
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function delete(array $mediatypeids): array {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $mediatypeids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_mediatypes = DB::select('media_type', [
			'output' => ['mediatypeid', 'name'],
			'mediatypeids' => $mediatypeids,
			'preservekeys' => true
		]);

		if (count($db_mediatypes) != count($mediatypeids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$actions = API::Action()->get([
			'output' => ['name'],
			'mediatypeids' => $mediatypeids,
			'limit' => 1
		]);

		if ($actions) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media types used by action "%1$s".', $actions[0]['name']));
		}

		DB::delete('media_type', ['mediatypeid' => $mediatypeids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_MEDIA_TYPE, $db_mediatypes);

		return ['mediatypeids' => $mediatypeids];
	}

	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		// adding message templates
		if ($options['selectMessageTemplates'] !== null && $options['selectMessageTemplates'] != API_OUTPUT_COUNT) {
			$message_templates = [];
			$relation_map = $this->createRelationMap($result, 'mediatypeid', 'mediatype_messageid',
				'media_type_message'
			);
			$related_ids = $relation_map->getRelatedIds();

			if ($related_ids) {
				$message_templates = API::getApiService()->select('media_type_message', [
					'output' => $options['selectMessageTemplates'],
					'mediatype_messageids' => $related_ids,
					'preservekeys' => true
				]);
				$message_templates = $this->unsetExtraFields($message_templates, ['mediatype_messageid', 'mediatypeid'],
					[]
				);
			}

			$result = $relation_map->mapMany($result, $message_templates, 'message_templates');
		}

		// adding users
		if ($options['selectUsers'] !== null && $options['selectUsers'] != API_OUTPUT_COUNT) {
			$users = [];
			$relationMap = $this->createRelationMap($result, 'mediatypeid', 'userid', 'media');
			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$users = API::User()->get([
					'output' => $options['selectUsers'],
					'userids' => $related_ids,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapMany($result, $users, 'users');
		}

		if ($this->outputIsRequested('parameters', $options['output'])) {
			foreach ($result as $mediatypeid => $mediatype) {
				$result[$mediatypeid]['parameters'] = [];
			}

			$parameters = DB::select('media_type_param', [
				'output' => ['mediatypeid', 'name', 'value'],
				'filter' => ['mediatypeid' => array_keys($result)]
			]);

			foreach ($parameters as $parameter) {
				$result[$parameter['mediatypeid']]['parameters'][] = [
					'name' => $parameter['name'],
					'value' => $parameter['value']
				];
			}
		}

		return $result;
	}

	/**
	 * Add existing webhook parameters and message templates to $db_mediatypes, regardless of whether they will be
	 * affected by the update.
	 *
	 * @static
	 *
	 * @param array $mediatypes
	 * @param array $db_mediatypes
	 */
	private static function addAffectedObjects(array $mediatypes, array &$db_mediatypes): void {
		$mediatypeids = ['parameters' => [], 'message_templates' => []];

		foreach ($mediatypes as $mediatype) {
			if (array_key_exists('parameters', $mediatype)) {
				$mediatypeids['parameters'][] = $mediatype['mediatypeid'];
				$db_mediatypes[$mediatype['mediatypeid']]['parameters'] = [];
			}

			if (array_key_exists('message_templates', $mediatype)) {
				$mediatypeids['message_templates'][] = $mediatype['mediatypeid'];
				$db_mediatypes[$mediatype['mediatypeid']]['message_templates'] = [];
			}
		}

		if ($mediatypeids['parameters']) {
			$options = [
				'output' => ['mediatype_paramid', 'mediatypeid', 'name', 'value'],
				'filter' => ['mediatypeid' => $mediatypeids['parameters']]
			];
			$db_params = DBselect(DB::makeSql('media_type_param', $options));

			while ($db_param = DBfetch($db_params)) {
				$db_mediatypes[$db_param['mediatypeid']]['parameters'][$db_param['mediatype_paramid']] =
					array_diff_key($db_param, array_flip(['mediatypeid']));
			}
		}

		if ($mediatypeids['message_templates']) {
			$options = [
				'output' => ['mediatype_messageid', 'mediatypeid', 'eventsource', 'recovery', 'subject', 'message'],
				'filter' => ['mediatypeid' => $mediatypeids['message_templates']]
			];
			$db_messages = DBselect(DB::makeSql('media_type_message', $options));

			while ($db_message = DBfetch($db_messages)) {
				$db_mediatypes[$db_message['mediatypeid']]['message_templates'][$db_message['mediatype_messageid']] =
					array_diff_key($db_message, array_flip(['mediatypeid']));
			}
		}
	}
}

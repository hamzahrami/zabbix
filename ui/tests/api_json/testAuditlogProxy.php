<?php
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup proxy
 */
class testAuditlogProxy extends testAuditlogCommon {

	/**
	 * Existing Proxy ID.
	 */
	private const PROXYID = 99000;

	/**
	 * Created proxy ID.
	 */
	private static $resourceid;

	public function testAuditlogProxy_Create() {
		$create = $this->call('proxy.create', [
			[
				'name' => 'Audit proxy',
				'mode' => PROXY_MODE_ACTIVE,
				'description' => 'Proxy audit description',
				'tls_accept' => 2,
				'allowed_addresses' => 'localhost',
				'tls_psk_identity' => 'Audit',
				'tls_psk' => '11111595725ac58dd977beef14b97461a7c1045b9a1c923453302c5473193478'
			]
		]);

		self::$resourceid = $create['result']['proxyids'][0];

		$created = json_encode([
			'proxy.name' => ['add', 'Audit proxy'],
			'proxy.description' => ['add', 'Proxy audit description'],
			'proxy.tls_accept' => ['add', '2'],
			'proxy.allowed_addresses' => ['add', 'localhost'],
			'proxy.tls_psk_identity' => ['add', '******'],
			'proxy.tls_psk' => ['add', '******'],
			'proxy.proxyid' => ['add', self::$resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid);
	}

	public function testAuditlogProxy_Update() {
		$updated = json_encode([
			'proxy.name' => ['update', 'Updated Audit proxy', 'API active proxy for discovery action'],
			'proxy.description' => ['update', 'Update proxy audit description', ''],
			'proxy.tls_accept' => ['update', '2', '1'],
			'proxy.allowed_addresses' => ['update', 'updated_address', ''],
			'proxy.tls_psk_identity' => ['update', '******', '******'],
			'proxy.tls_psk' => ['update', '******', '******']
		]);

		$this->call('proxy.update', [
			[
				'proxyid' => self::PROXYID,
				'name' => 'Updated Audit proxy',
				'mode' => PROXY_MODE_ACTIVE,
				'description' => 'Update proxy audit description',
				'tls_accept' => 2,
				'allowed_addresses' => 'updated_address',
				'tls_psk_identity' => 'Updated_TSK',
				'tls_psk' => '11111595725ac58dd977beef14b97461a7c1045b9a1c923453302c5473193111'
			]
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::PROXYID);
	}

	/**
	 * @depends testAuditlogProxy_Create
	 */
	public function testAuditlogProxy_Delete() {
		$this->call('proxy.delete', [self::$resourceid]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'Audit proxy', self::$resourceid);
	}
}

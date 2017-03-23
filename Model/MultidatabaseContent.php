<?php
/**
 * MultidatabaseContent Model
 * 汎用データベースコンテンツデータに関するモデル処理
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Tomoyuki OHNO (Ricksoft Co., Ltd.) <ohno.tomoyuki@ricksoft.jp>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('MultidatabasesAppModel', 'Multidatabases.Model');
App::uses('MultidatabaseModel', 'Multidatabase.Model');
App::uses('MultidatabaseMetadataModel', 'MultidatabaseMetadata.Model');
App::uses('TemporaryFolder', 'Files.Utility');

/**
 * MultidatabaseContent Model
 *
 * @author Tomoyuki OHNO (Ricksoft, Co., Ltd.) <ohno.tomoyuki@ricksoft.jp>
 * @package NetCommons\Multidatabases\Model
 *
 */
class MultidatabaseContent extends MultidatabasesAppModel {

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = [];

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = [
		/*
		'Multidatabase' => array(
			'className' => 'Multidatabases.Multidatabase',
			'foreignKey' => 'multidatabase_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
		'Language' => array(
			'className' => 'M17.Language',
			'foreignKey' => 'language_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
		*/
		'Block' => [
			'className' => 'Blocks.Block',
			'foreignKey' => 'block_id',
			'conditions' => '',
			'fields' => '',
			'order' => '',
			'counterCache' => [
				'content_count' => [
					//'MultidatabaseContent.is_origin' => true,
					'MultidatabaseContent.is_latest' => true,
				],
			],
		],
	];

/**
 * Behavior
 *
 * @var array
 */
	public $actsAs = [
		'NetCommons.Trackable',
		'NetCommons.OriginalKey',
		'Workflow.Workflow',
		'Likes.Like',
		'Workflow.WorkflowComment',
		'ContentComments.ContentComment',
		'Files.Attachment',
	];

/**
 * @var array 絞り込みフィルタ保持値
 */
	protected $_filter = [
		'status' => 0,
	];

/**
 * MultidatabaseContent constructor.
 *
 * @param array|bool|int|string $request
 * @param null|string $response
 * @return void
 */
	public function __construct() {
		parent::__construct();

		$this->loadModels([
			'MultidatabaseMetadata' => 'Multidatabases.MultidatabaseMetadata',
		]);

		$searchContents = $this->MultidatabaseMetadata->getSearchMetadatas();

		$this->Behaviors->load('Topics.Topics',[
			'fields' => [
				'title' => 'MultidatabaseContent.value1',
				'summary' => 'MultidatabaseContent.value1',
				'path' => '/:plugin_key/multidatabase_contents/detail/:block_id/:content_key',
			],
			'search_contents' => $searchContents
		]);

		$this->Behaviors->load('Mails.MailQueue',[
			'embedTags' => [
				'X-SUBJECT' => 'MultidatabaseContent.value1',
				'X-BODY' => 'MUltidatabaseContent.value1',
				'X-URL' => [
					'controller' => 'multidatabase_contents'
				]
			],
		]);
	}

/**
 * Before validate
 *
 * @param array $options オプション
 * @return bool
 */
	public function beforeValidate($options = []) {
		$this->validate = $this->makeValidation();

		return parent::beforeValidate($options);
	}

/**
 * 編集用のデータを取得する
 * @param array $conditions データ取得条件
 * @return array|bool
 */
	public function getEditData($conditions = []) {
		$this->loadModels([
			'Multidatabase' => 'Multidatabases.Multidatabase',
			'MultidatabaseMetadata' => 'Multidatabases.MultidatabaseMetadata',
		]);

		if (empty($conditions)) {
			return false;
		}

		$options = [
			'conditions' => $conditions,
			'recursive' => 0,
		];

		$content = $this->find('first', $options);

		if (!$content) {
			return false;
		}

		if (!$multidatabase = $this->Multidatabase->getMultidatabase()) {
			return false;
		}

		$metadatas = $this->MultidatabaseMetadata->getEditMetadatas(
			$multidatabase['Multidatabase']['id']
		);

		if (!$metadatas) {
			return false;
		}

		foreach ($metadatas as $metadata) {
			if (
				isset($content['MultidatabaseContent']['value' . $metadata['col_no']]) &&
				$content['MultidatabaseContent']['value' . $metadata['col_no']] <> ''
			) {
				$tmpValue  = $content['MultidatabaseContent']['value' . $metadata['col_no']];
				switch ($metadata['type']) {
					case 'radio':
					case 'select':
						$content['MultidatabaseContent']['value' . $metadata['col_no']]
							= md5($tmpValue);
						break;
					case 'checkbox' :
						$tmpValArr = explode('||', $tmpValue);
						$tmpValRes = [];
						foreach ($tmpValArr as $val) {
							$tmpValRes[] = md5($val);
						}
						$content['MultidatabaseContent']['value' . $metadata['col_no']]
							= $tmpValRes;
						break;
					default:
						break;
				}
			}
		}

		return $content;
	}

/**
 * Get contents
 * 複数のコンテンツを取得
 *
 * @return array|bool
 */
	public function getMultidatabaseContents() {
		$this->loadModels([
			'Multidatabase' => 'Multidatabases.Multidatabase',
		]);

		if (!$multidatabase = $this->Multidatabase->getMultidatabase()) {
			return false;
		}

		$result = $this->find('all', [
			'recursive' => 0,
			'conditions' => [
				'multidatabase_key' => $multidatabase['Multidatabase']['key'],
			],
		]);

		return $result;
	}

/**
 * Make validation rules
 * バリデーションルールの作成
 *
 * @return array|bool
 */
	public function makeValidation() {
		$this->loadModels([
			'MultidatabaseMetadata' => 'Multidatabases.MultidatabaseMetadata',
			'Multidatabase' => 'Multidatabases.Multidatabase',
		]);

		if (!$multidatabase = $this->Multidatabase->getMultidatabase()) {
			return false;
		}

		if (!$multidatabaseMetadatas =
			$this->MultidatabaseMetadata->getEditMetadatas(
				$multidatabase['Multidatabase']['id']
			)
		) {
			return false;
		}

		$result = [];
		foreach ($multidatabaseMetadatas as $metadata) {
			if ($metadata['is_require']) {
				$tmp = [];
				switch ($metadata['type']) {
					case 'checkbox':
						$tmp['rule'] = [
							'multiple',
							[
								'min' => 1,
							],
						];
						break;
					default:
						$tmp['rule'][] = 'notBlank';
						$tmp['allowEmpty'] = false;
						break;
				}
				$tmp['required'] = true;
				$result['value' . $metadata['col_no']] = $tmp;
			}
		}

		return Hash::merge($this->validate, $result);
	}

/**
 * Clear values
 * 削除対象カラムに存在する値をクリアする
 *
 * @param string $multidatabaseKey 汎用データベースKey（プラグインキー）
 * @param array $colNos 列番号
 * @return bool
 * @throws InternalErrorException
 */
	public function clearValues($multidatabaseKey = null, $colNos = []) {
		if (
			is_null($multidatabaseKey)
			|| empty($currentMetadatas)
		) {
			return false;
		}

		$conditions['multidatabase_key'] = $multidatabaseKey;

		$data = [];
		foreach ($colNos as $colNo) {
			$data['value' . $colNo] = '';
		}

		if (!$this->updateAll($data, $conditions)) {
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}

		return true;
	}

/**
 * Save content
 * コンテンツを保存する
 *
 * @param array $data 保存するコンテンツデータ
 * @return bool|array
 * @throws InternalErrorException
 */
	public function saveContent($data) {
		$this->loadModels([
			'Multidatabase' => 'Multidatabases.Multidatabase',
			'MultidatabaseMetadata' => 'Multidatabases.MultidatabaseMetadata',
		]);

		if (! $multidatabase = $this->Multidatabase->getMultidatabase()) {
			return false;

		}
		if (! $metadatas = $this->MultidatabaseMetadata->getMetadatasColNo(
			$multidatabase['Multidatabase']['id'])
		) {
			return false;
		}

		$this->set($data);

		if (!$this->validates()) {
			return false;
		}

		$multidatabaseContent = $data['MultidatabaseContent'];
		foreach ($multidatabaseContent as $key => $val) {
			if (strstr($key,'value') <> false) {
				$colNo = (int)str_replace('value','',$key);

				if (isset($metadatas[$colNo])) {
					$selections = [];
					if (isset($metadatas[$colNo]['selections'])) {
						$selections = json_decode($metadatas[$colNo]['selections'],true);
					}

					switch ($metadatas[$colNo]['type']) {
						case 'select':
							$tmp = $data['MultidatabaseContent'][$key];
							foreach ($selections as $metaSel) {
								if (md5($metaSel) === $tmp) {
									$data['MultidatabaseContent'][$key] = $metaSel;
									break;
								}
							}
							break;
						case 'checkbox':
							if (empty($tmpArr)) {
								$data['MultidatabaseContent'][$key] = '';
								break;
							}
							$tmpArr = $data['MultidatabaseContent'][$key];
							$tmpRes = [];
							foreach ($selections as $metaSel) {
								if (in_array(md5($metaSel),$tmpArr)) {
									$tmpRes[] = $metaSel;
								}
							}
							if (empty($tmpRes)) {
								$data['MultidatabaseContent'][$key] = '';
							} else {
								$data['MultidatabaseContent'][$key] = implode('||', $tmpRes);
							}
							break;
						case 'file':
						case 'image':
							$this->uploadSettings($key . '_attach');
							$data['MultidatabaseContent'][$key] = '';
							break;
						default:
							break;
					}
				}
			}
		}

		$this->begin();
		try {
			//$this->create();
			if (($savedData = $this->save($data, false)) === false) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}
			$this->commit();
		} catch (Exception $e) {
			$this->rollback($e);
		}

		return $savedData;
	}

/**
 * Get File download URL
 * ファイルダウンロードURLを出力
 *
 * @return void
 */
	function getFileURL() {
		$contentKey = $this->request->params['pass'][0];
		$options['field'] = $this->request->params['pass'][1];
		$options['size'] = Hash::get($this->request->params['pass'], 2, 'medium');
		return $this->Download->doDownload($contentKey,$options);
	}

/**
 * Get conditions
 * UserIdと権限から参照可能なEntryを取得するCondition配列を返す
 *
 * @param int $blockId ブロックID
 * @param array $permissions 権限
 * @return array condition
 */
	public function getConditions($blockId, $permissions) {
		// contentReadable falseなら何も見えない
		if ($permissions['content_readable'] === false) {
			$conditions = ['MultidatabaseContent.id' => 0];

			return $conditions;
		}

		// デフォルト絞り込み条件
		$conditions = [
			'MultidatabaseContent.block_Id' => $blockId,
		];

		$conditions = $this->getWorkflowConditions($conditions);

		return $conditions;
	}

/**
 * Delete content
 * コンテンツの削除
 *
 * @param string $key コンテンツキー
 * @return bool
 * @throws InternalErrorException
 */
	public function deleteContentByKey($key) {
		$this->begin();

		$result = false;

		try {
			$this->contentKey = $key;

			$conditions = [
				'MultidatabaseContent.Key' => $key,
			];

			if ($result = $this->deleteAll($conditions, true, true)) {
				$this->commit();
			} else {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}
		} catch (Exception $e) {
			$this->rollback($e);
		}

		return $result;
	}
}

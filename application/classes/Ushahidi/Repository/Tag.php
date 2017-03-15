<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Ushahidi Tag Repository
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use Ushahidi\Core\Entity;
use Ushahidi\Core\SearchData;
use Ushahidi\Core\Entity\Tag;
use Ushahidi\Core\Usecase\Tag\UpdateTagRepository;
use Ushahidi\Core\Usecase\Tag\DeleteTagRepository;
use Ushahidi\Core\Usecase\Post\UpdatePostTagRepository;

class Ushahidi_Repository_Tag extends Ushahidi_Repository implements
	UpdateTagRepository,
	DeleteTagRepository,
	UpdatePostTagRepository
{
	// Use the JSON transcoder to encode properties
	use Ushahidi_JsonTranscodeRepository;

	private $created_id;
	private $created_ts;

	private $deleted_tag;

	// Ushahidi_Repository
	protected function getTable()
	{
		return 'tags';
	}

	// CreateRepository
	// ReadRepository
	public function getEntity(Array $data = null)
	{
		if(isset($data['parent_id'])){
			$data['parent'] = $this->getParent($data['parent_id']);
		}
		$data['surveys'] = $this->getFormsForTag($data['id']);
		$tag = new Tag($data);
		Kohana::$log->add(Log::ERROR, print_r($tag, true));
		return $tag;
	}

	private function getFormsForTag($id) {
		$result = DB::select('form_id') ->from('forms_tags')
			->where('tag_id', '=', $id)
			->execute($this->db);
		return $result->as_array(NULL, 'form_id');
	}

	// Ushahidi_JsonTranscodeRepository
	protected function getJsonProperties()
	{
		return ['role'];
	}

	// SearchRepository
	public function getSearchFields()
	{
		return ['tag', 'type', 'parent_id', 'q', /* LIKE tag */];
	}

	// Ushahidi_Repository
	protected function setSearchConditions(SearchData $search)
	{
		$query = $this->search_query;

		foreach (['tag', 'type', 'parent_id'] as $key)
		{
			if ($search->$key) {
				$query->where($key, '=', $search->$key);
			}
		}

		if ($search->q) {
			// Tag text searching
			$query->where('tag', 'LIKE', "%{$search->q}%");
		}
	}
	
	// SearchRepository
	public function getSearchResults()
	{
		$query = $this->getSearchQuery();
		$results = $query->distinct(TRUE)->execute($this->db);
		return $this->getCollection($results->as_array());
	}
	
	protected function getParent($id)
	{
		$tag = DB::select('tag')->from('tags')
				->where('id', '=', $id)
				->execute($this->db)
				->get('tag', NULL);

		return [
			'id' => $id,
			'url' => URL::site(Ushahidi_Rest::url($this->getTable(), $id), Request::current()),
			'tag' => $tag
		];
	}

	// CreateRepository
	public function create(Entity $entity)
	{
		$record = $entity->asArray();
		$record['created'] = time();
		//unset forms
		unset($record['surveys']);
		$id = $this->executeInsert($this->removeNullValues($record));

		if($entity->surveys) {
			$this->updateTagForms($id, $entity->surveys);
		}

		return $id;
	}

	protected function updateTagForms($tag_id, $forms) {
		if(empty($forms)) {
			DB::delete('forms_tags')
				->where('tag_id', '=', $tag_id)
				->execute($this->db);
		} else{
			// create method
			// $existing = $this->getFormsForTag($tag_id);
			$insert = DB::insert('forms_tags', ['form_id', 'tag_id']);
			$form_ids = [];
			$new_forms = FALSE;

			foreach($forms as $form) {
				$insert->values([$form, $tag_id]);
				$new_forms = TRUE;
			}
			if($new_forms)
			{
				$insert->execute($this->db);
			}
		}
	}
// 	public function update(Entity $entity)
// 	{
// 		$tag = $entity->getChanged();

// 		if($entity->hasChanged('surveys'))
// 		{
// 			$surveys = $entity->surveys;
// 			Kohana::$log->add(Log::ERROR, print_r($surveys, true));
// 			if(empty($surveys)){
// 				DB::delete('forms_tags')
// 				->where('tag_id', '=', $entity->id)
// 				->execute($this->db);
// 			}
// 			else
// 			{
// 				$existing = DB::select('form_id')->from('forms_tags')
// 				->where('tag_id', '=', $entity->id)
// 				->execute($this->db)
// 				->as_array(NULL 'form_id');
// 			}
// 		// 		$insert = DB::insert('forms_tags', ['tag_id', 'form_id']);

// 		// 		$form_ids = [];
// 		// 		$new_forms = FALSE;
// 		// 		foreach($surveys as $survey)
// 		// 		{
// 		// 			if(is_array($survey)
// 		// 			{
// 		// 				$survey = $survey['id']
// 		// 			}

// 		// 		}


// 		// 	}
// 		// }
// 		// Kohana::$log->add(Log::ERROR, print_r($entity, true));	
// 		// Kohana::$log->add(Log::ERROR, print_r($tag, true));	
// 	}
// }
	// UpdatePostTagRepository
	public function getByTag($tag)
	{
		return $this->getEntity($this->selectOne(compact('tag')));
	}

	// UpdatePostTagRepository
	public function doesTagExist($tag_or_id)
	{
		$query = $this->selectQuery()
			->select([DB::expr('COUNT(*)'), 'total'])
			->where('id', '=', $tag_or_id)
			->or_where('tag', '=', $tag_or_id)
			->execute($this->db);

		return $query->get('total') > 0;
	}

	// UpdateTagRepository
	public function isSlugAvailable($slug)
	{
		return $this->selectCount(compact('slug')) === 0;
	}

	// DeleteTagRepository
	public function deleteTag($id)
	{
		return $this->delete(compact('id'));
	}
}

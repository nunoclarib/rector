<?php

namespace Campus\Lib\Graph;

use Campus\ActivityStreams;
use Campus\CampusActivityStreams\MediaLinkFactory;
use Campus\Config\Config;
use Campus\Lib\Graph\Property\iPrivacy;
use Campus\Lib\Graph;
use Campus\Lib\Logger;
use Campus\Tool\StringUtils;
use Campus\Tool\ApiParser;
use Campus\Tool\Url;
use Everyman\Neo4j;
use \Datetime;
use Campus\Lib\Logger\Registry;

abstract class Node extends Neo4j\Node
{
	final const PROPERTY_ID = 'id';
	final const PROPERTY_URL = 'url';
	final const PROPERTY_IMAGE = 'image';
	final const PROPERTY_CONTENT = 'content';
	final const PROPERTY_SUMMARY = 'summary';
	final const PROPERTY_ATTACHMENTS = 'attachments';
	final const PROPERTY_VERB = 'verb';
	final const PROPERTY_PRIVACY = 'privacy';
	final const PROPERTY_HEIGHT = 'height';
	final const PROPERTY_WIDTH = 'width';
	final const PROPERTY_CATEGORY = 'category';

	final const PROPERTY_ACTIVE = 'active';
	final const PROPERTY_INACTIVE = 'inactive';

	final const PROPERTY_HIGHLIGHTED = 'highlighted';
	final const PROPERTY_ROOT = 'root';

	final const PROPERTY_INVALID = 'invalid';
	final const PROPERTY_VERSION = 'version';
	final const PROPERTY_REACTION_TYPE = 'type';


	/**
	 * Published property name.
	 * Value will be the node creation unix timestamp.
	 */
	final const PROPERTY_OBJECT_TYPE = 'objectType';

	/**
	 * Published property name.
	 * Value will be the node creation unix timestamp.
	 */
	final const PROPERTY_PUBLISHED = 'published';

	/**
	 * DisplayName property name
	 * Value will be the node display name.
	 */
	final const PROPERTY_DISPLAY_NAME = 'displayName';

	/**
	 * Normalized prefix
	 */
	final const PROPERTY_PREFIX_NORMALIZED = 'normalized_';

	/**
	 * Updated property name.
	 * Value will be the node last update unix timestamp.
	 */
	final const PROPERTY_UPDATED = 'updated';

	final const PROPERTY_TIMES = 'times';

	final const PROPERTY_SEEN = 'seen';
	/**
	 * Order the result by date from most recent to older
	 */
	final const ORDER_DATE = 'date';

	/**
	 * Order the result by date from older to most recent
	 */
	final const ORDER_DATE_REVERSE = 'date-reverse';

	/**
	 * Order the result alphabetically from A to Z
	 */
	final const ORDER_ALPHABETICAL = 'alphabetical';

	/**
	 * Order the result alphabetically from Z to A
	 */
	final const ORDER_ALPHABETICAL_REVERSE = 'alphabetical-reverse';

	/**
	 * Paginate by an offset amount
	 */
	final const PAGINATION_OFFSET = 'offset';

	/**
	 * Paginate by a specific ID (get activity before it)
	 */
	final const PAGINATION_BEFORE_ID = 'before-id';

	/**
	 * Paginate by a specific ID (get activity after it)
	 */
	final const PAGINATION_AFTER_ID = 'after-id';

	/**
	 * Paginate by a published date (get activity before it)
	 */
	final const PAGINATION_BEFORE_PUBLISHED = 'before-published';

	/**
	 * Paginate by a published date (get activity after it)
	 */
	final const PAGINATION_AFTER_PUBLISHED = 'after-published';

	/**
	 * Paginate by an updated date (get activity before it)
	 */
	final const PAGINATION_BEFORE_UPDATED = 'before-updated';

	/**
	 * Paginate by an updated date (get activity after it)
	 */
	final const PAGINATION_AFTER_UPDATED = 'after-updated';

	final const CONTEXT_ORGANIZATION = 'organization';
	final const CONTEXT_GROUP = 'group';
	final const CONTEXT_PERSON = 'person';
	final const CONTEXT_PLATFORM = 'platform';
	final const CONTEXT_HUB = 'hub';
	final const CONTEXT_DISCUSSION = 'discussion';

	final const OBJECT_TYPE_SELF = 'self';
	final const OBJECT_TYPE_ORGANIZATION = 'organization';
	final const OBJECT_TYPE_GROUP = 'group';
	final const OBJECT_TYPE_HUB = 'hub';
	final const OBJECT_TYPE_QUESTION = 'question';
	final const OBJECT_TYPE_SERVICE = 'service';
	final const OBJECT_TYPE_PERSON = 'person';
	final const OBJECT_TYPE_CONTENT = 'content';
	final const OBJECT_TYPE_FOLDER = 'folder';
	final const OBJECT_TYPE_EVENT = 'event';
	final const OBJECT_TYPE_DISCUSSION = 'discussion';

	public static $objectType;
	public static $dataQuery;
	public static $actorDataQuery;

	public static $propertiesToNormalize = array(
		self::PROPERTY_DISPLAY_NAME
	);

	protected $loadedRelatedData = false;

	public function __construct()
	{
		parent::__construct(Client::getInstance());
	}

	public function __wakeup()
	{
		if (!isset($this->client)) {
			$this->client = Client::getInstance();
		}
	}

	/**
	 * @return string
	 */
	public function getObjectId()
	{
		$id = $this->getProperty('id');
		return $id;
	}

	/**
	 * @return string
	 */
	public function getContactEmail()
	{
		$contactEmail = $this->getProperty('contactEmail');
		return $contactEmail;
	}

	/**
	 * @return string
	 */
	public function getObjectType()
	{
		$objectType = $this->getProperty('objectType');
		return $objectType;
	}

	/**
	 * @return string
	 */
	public function getPrivacy()
	{
		return $this->getProperty(Property\iPrivacy::PROPERTY_PRIVACY);
	}

	/**
	 * @param string $property
	 * @return ActivityStreams\MediaLink
	 */
	public function getImage($property = 'image')
	{
		$mediaLink = MediaLinkFactory::getForObjectTypeAndPropertyName(
			$this->getProperty("{$property}_url"),
			$this->getObjectType(),
			$property,
			$this->getProperty("{$property}_blur")
		);

		$mediaLink->width = $this->getProperty("{$property}_width");
		$mediaLink->height = $this->getProperty("{$property}_height");

		return $mediaLink;
	}

	/**
	 * @return string
	 */
	public function getOgImage()
	{
		// dd($this);
		return $this->getProperty("img_url") ? $this->getProperty("img_url") : $this->getProperty("image_url");
	}

	/**
	 * @return array
	 */
	protected function getPropertyNamesForIndex()
	{
		return array(
			'id',
			'url',
			'objectType',
		);
	}

	public static function create(ActivityStreams\Object $object)
	{


		unset($object->author);
		$properties = self::flatten(json_decode(json_encode($object), true));
		$properties = Node::normalizeProperties($properties, static::$propertiesToNormalize);

		/** @var Node $node */
		$node = new static();
		$node->setProperties($properties);

		if (!isset($properties[self::PROPERTY_PUBLISHED])) {
			$node->setProperty(self::PROPERTY_PUBLISHED, time());
		}
		if (!isset($properties[self::PROPERTY_UPDATED])) {
			$node->setProperty(self::PROPERTY_UPDATED, $node->getProperty(self::PROPERTY_PUBLISHED));
		}

		$node->setProperty(self::PROPERTY_OBJECT_TYPE, static::$objectType);

		$node->save();

		$labels = array();

		$objectTypeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);
		$labels[] = Client::getLabelInstance($objectTypeLabel);

		if (isset($properties[iPrivacy::PROPERTY_PRIVACY])) {
			$privacyLabel = strtoupper((string) $properties[iPrivacy::PROPERTY_PRIVACY]);
			$labels[] = Client::getLabelInstance($privacyLabel);
		}

		if (isset($properties[self::PROPERTY_URL])) {
			$labels[] = Client::getLabelInstance(strtoupper(self::PROPERTY_URL));
		}
		Registry::get('mentions.cenas')->debug("LABELS:    " . serialize($labels));
		$node->addLabels($labels);

		// Create the origin relationship with the source node
		$sourceNode = Node\Source::getById(Graph\Client::getQueryDataSourceId());
		Relationship\Origin::create($node, $sourceNode);

		$logger = Logger\Registry::get('graph.inserted');
		$logger->info(
			'(N): ' . $node->getObjectType() . ' ' . $node->getObjectId() . ' | ' .
				Client::graphWebUiSearchURL($node->getId())
		);

		return $node;
	}

	public function update(ActivityStreams\Object $object)
	{
		unset($object->author);

		$properties = self::flatten(json_decode(json_encode($object), true));
		$properties = Node::normalizeProperties($properties, static::$propertiesToNormalize);

		//
		// Change privacy label
		//
		if (isset($properties[iPrivacy::PROPERTY_PRIVACY])) {
			$privateLabel = Client::getLabelInstance('PRIVATE');
			$publicLabel = Client::getLabelInstance('PUBLIC');
			$this->removeLabels(array($privateLabel, $publicLabel));

			$privacyLabel = Client::getLabelInstance(strtoupper((string) $properties[iPrivacy::PROPERTY_PRIVACY]));
			$this->addLabels(array($privacyLabel));
		}

		foreach ($properties as $key => $value) {
			if (isset($value)) continue;

			$this->removeProperty($key);
			unset($properties[$key]);
		}

		foreach ($properties as $key => &$value) {
			if (stripos((string) $key, 'url') !== false) $value = Url::prepareForStorage($value);
		}

		$this->setProperties($properties);

		// autch.
		unset($this->properties['taggedPersons']);

		return $this->save();
	}

	/**
	 * @return Neo4j\PropertyContainer
	 */
	public function delete()
	{
		$skip = 0;
		$step = 100;
		do {
			$query = "MATCH (root)-[rel]-() RETURN rel SKIP {$skip} LIMIT {$step}";
			$query = $this->prepareQuery($query);
			$data = Graph\Client::cypherQuery($query, array('id' => $this->getObjectId()));

			/** @var Neo4j\Query\Row $row */
			foreach ($data as $row) {
				/** @var Relationship $rel */
				$rel = $row->current();
				$rel->delete();
			}
			$skip = $skip + $step;
		} while ($data->count() === $step);

		return parent::delete();
	}

	/**
	 * @param bool $dehydrated
	 * @param Node\Person $actorNode
	 * @param bool $withComments
	 *
	 * @return ActivityStreams\Object
	 * @throws \Exception
	 */
	public function returnAsActivityStreamsObject($dehydrated = false, $actorNode = null, $withComments = false)
	{
		$properties = $this->getProperties();
		$properties = $this->expand($properties);

		if ($this instanceof Node\Interfaces\Pinnable) {
			$properties = array_merge($properties, $this->getPinnedInfoPropertiesArray());
		}

		// order attachments...
		ksort($properties, SORT_NATURAL);
		/** @var ActivityStreams\Object $object */
		$object = $this->propertiesArrayToActivityStreams($properties, null, false, $dehydrated, $actorNode);

		// Setting the author node inside the object
		if ($this->authorNode instanceof Node\Person) {
			$object->author = $this->authorNode->returnAsActivityStreamsObject($dehydrated, $actorNode, $withComments);
		}

		if ($this instanceof Node\Person && isset($object->summary) && strlen((string) $object->summary) > 200) {
			$object->summary = StringUtils::truncate(@$object->summary, 200, '(...)');
		}

		$isRichContent = $this instanceof Node\Article || $this instanceof Node\Page || $this instanceof Node\Comment || $this instanceof Node\Task;

		if (isset($object->content)) {

			if (($this instanceof Node\Mission || $this instanceof Node\MissionCollection || $this instanceof Node\Condition) && !is_null($actorNode)) {

				$object->content = strtr($object->content, [
					'{$userId}' => $actorNode->getObjectId()
				]);
			} else {
				$object->content = ApiParser::parseEverything($object->content, $object->gcp('taggedPersons'), $isRichContent);
			}
		}

		if (isset($object->summary)) {
			if (($this instanceof Node\Mission || $this instanceof Node\MissionCollection || $this instanceof Node\Condition) && isset($actorNode)) {

				$object->summary = strtr($object->summary, [
					'{$userId}' => $actorNode->getObjectId()
				]);
			} else {
				$object->summary = ApiParser::parseEverything($object->summary, $object->gcp('taggedPersons'), $isRichContent);
			}
		}
		return $object;
	}
	/**
	 * @param bool $dehydrated
	 * @param Node\Person $actorNode
	 * @param bool $withComments
	 *
	 * @return ActivityStreams\Object
	 * @throws \Exception
	 */
	public function returnAsActivityStreamsObjectWarmup($dehydrated = false, $actorNode = null, $withComments = false)
	{
		$properties = $this->getProperties();
		$properties = $this->expand($properties);

		ksort($properties, SORT_NATURAL);
		/** @var ActivityStreams\Object $object */
		$object = $this->propertiesArrayToActivityStreams($properties, null, false, $dehydrated, $actorNode);

		return $object;
	}

	/**
	 * @param bool $dehydrated
	 * @param null $actorNode
	 * @param bool $withComments
	 *
	 * @return ActivityStreams\Activity|mixed
	 * @throws \Exception
	 */
	public function returnAsActivityStreamsActivity($dehydrated = false, $actorNode = null, $withComments = false)
	{
		$activity = new ActivityStreams\Activity();
		if ($dehydrated) $activity = ActivityStreams\Dehydrator::trimNonScalar($activity);

		if ($this->providerNode instanceof Node) {
			$activity->provider = $this->providerNode->returnAsActivityStreamsObject($dehydrated, $actorNode, $withComments);
			if (property_exists($this, 'hostNode') && $this->hostNode instanceof Node\Organization) {
				$activity->provider->{'@host'} = $this->hostNode->returnAsActivityStreamsObject($dehydrated, $actorNode, $withComments);
			}
		}

		if ($this->targetNode instanceof Node) {
			$activity->target = $this->targetNode->returnAsActivityStreamsObject($dehydrated, $actorNode, $withComments);
		}

		if (is_array($this->attachmentNodes)) {
			$activity->attachments = array();

			/** @var Node $attachment */
			foreach ($this->attachmentNodes as $attachment) {
				if (!$attachment instanceof Node) continue;

				$attachmentObject = $attachment->returnAsActivityStreamsObject($dehydrated, $actorNode, $withComments);
				if (!empty($this->appendedNode)) {
					$attachmentObject->attachments = array($this->appendedNode->returnAsActivityStreamsObject($dehydrated, $actorNode, $withComments));
				}

				$activity->attachments[] = $attachmentObject;
			}
		}

		$activity->object = $this->returnAsActivityStreamsObject($dehydrated, $actorNode, $withComments);
		if (isset($activity->object->author)) $activity->actor = $activity->object->author;

		if ($withComments) {
			$comments = Node\Comment::findByObjectTypeIdSingleLevel($activity->object->objectType, $activity->object->id)->setLimit(1);
			$comments = $comments->find()->returnAsActivityStreamsCollection(true, $dehydrated, $actorNode, $withComments);
			$comments->items = array_reverse($comments->items);
			$activity->setCustomProperty('comments', $comments);
		}

		return $activity;
	}
	/**
	 * @param bool $dehydrated
	 * @param null $actorNode
	 * @param bool $withComments
	 *
	 * @return ActivityStreams\Activity|mixed
	 * @throws \Exception
	 */
	public function returnAsActivityStreamsActivityWarmup($dehydrated = false, $actorNode = null, $withComments = false)
	{
		$activity = new ActivityStreams\Activity();
		if ($dehydrated) $activity = ActivityStreams\Dehydrator::trimNonScalar($activity);

		$activity->object = $this->returnAsActivityStreamsObjectWarmup($dehydrated, $actorNode, $withComments);

		if($activity->object->objectType === "service"){
			if ($this->providerNode instanceof Node) {
				//var_dump($this->providerNode);
				$activity->providerId = $this->providerNode->properties['id'];
				$activity->providerObjectType = $this->providerNode->properties['objectType'];
			}
		}

		if (is_array($this->attachmentNodes)) {
			$activity->attachments = array();

			/** @var Node $attachment */
			foreach ($this->attachmentNodes as $attachment) {
				if (!$attachment instanceof Node) continue;

				$attachmentObject = $attachment->returnAsActivityStreamsObject($dehydrated, $actorNode, $withComments);
				if (!empty($this->appendedNode)) {
					$attachmentObject->attachments = array($this->appendedNode->returnAsActivityStreamsObject($dehydrated, $actorNode, $withComments));
				}

				$activity->attachments[] = $attachmentObject;
			}
		}

		if (isset($activity->object->author)) $activity->actor = $activity->object->author;

		return $activity;
	}
	/**
	 * return array
	 */
	public function returnAsOpenGraphArray()
	{
		//TODO: CHECK IF IS PUBLIC

		if ($this->checkContextOgPrivacy()) {
			return [
				"id" => $this->getObjectId(),
				"title" => $this->getProperty('displayName'),
				"url" => "https:" . $this->getProperty('url'),
				"image" => $this->getOgImage(),
				"description" => $this->getProperty('summary'),
			];
		}

		throw new \Exception('Object is not public');
	}

	/**
	 * @param string $id
	 * @return static
	 */
	public static function getById($id)
	{

		$result = static::findById($id)->find();
		$objectNode = ($result && count($result))
			? $result->current()
			: false;

		return $objectNode;
	}

	/**
	 * @param string $id
	 * @return static
	 */
	public static function getByIdAnyPlatform($id)
	{
		$result = static::findByIdAnyPlatform($id)->find();
		$objectNode = ($result && count($result))
			? $result->current()
			: false;

		return $objectNode;
	}

	/**
	 * @param string $id
	 * @return static
	 */
	public static function getByIdAnyState($id)
	{
		$result = static::findByIdAnyState($id)->find();
		$objectNode = ($result && count($result))
			? $result->current()
			: false;

		return $objectNode;
	}

	/**
	 * @param string $id
	 * @param Node\Person $actorNode
	 * @return static
	 */
	public static function getByIdForActor($id, $actorNode = null)
	{
		if (!$actorNode) return static::getById($id);

		$result = static::findByIdForActor($id, $actorNode)->find();

		$objectNode = ($result && count($result))
			? $result->current()
			: false;

		return $objectNode;
	}

	/**
	 * @param array $idArray
	 * @return array
	 */
	public static function getByIdArray($idArray)
	{
		$result = static::findByIdArray($idArray)->find();
		$objectNodes = ($result && count($result))
			? $result->returnAsArray()
			: false;

		return $objectNodes;
	}

	/**
	 * @param array $idArray
	 * @param Node\Person $actorNode
	 * @return array|bool|Node[]
	 */
	public static function getByIdArrayForActor($idArray, $actorNode = null): array|bool
	{
		if (!$actorNode) {
			return static::getByIdArray($idArray);
		}

		$result = static::findByIdArrayForActor($idArray, $actorNode)->find();
		$objectNodes = ($result && count($result))
			? $result->returnAsArray()
			: false;

		return $objectNodes;
	}

	/**
	 * @param string $id
	 * @return Graph\NodeFinder
	 */
	public static function findById($id)
	{
		$nodeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);
		$query = "
			MATCH (objectNode:{$nodeLabel} {id:{id}})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
			WITH objectNode
		";
		$params = array('id' => $id);

		$finder = new Graph\NodeFinder(new Graph\Result(), static::$objectType, $query, $params);
		$finder = $finder->setNodeDataQuery(static::$dataQuery)
			->setNoQueryByNodeId();

		return $finder;
	}

	/**
	 * @param string $id
	 * @return Graph\NodeFinder
	 */
	public static function findByIdAnyPlatform($id)
	{
		$nodeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);
		$query = "
			MATCH (objectNode:{$nodeLabel} {id:{id}})
			WITH objectNode
		";
		$params = array('id' => $id);

		$finder = new Graph\NodeFinder(new Graph\Result(), static::$objectType, $query, $params);
		$finder = $finder->setNodeDataQuery(static::$dataQuery)
			->setNoQueryByNodeId();

		return $finder;
	}

	/**
	 * @param string $id
	 * @return Graph\NodeFinder
	 */
	public static function findByIdAnyState($id)
	{
		$nodeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);
		$query = "
			MATCH (objectNode:{$nodeLabel} {id:{id}})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
			WITH objectNode
		";
		$params = array('id' => $id);

		$finder = new Graph\NodeFinder(new Graph\Result(), static::$objectType, $query, $params);
		$finder = $finder->setNodeDataQuery(static::$adminDataQuery)
			->setNoQueryByNodeId();

		return $finder;
	}

	/**
	 * @param string $id
	 * @param Node\Person $actorNode
	 * @return Graph\NodeFinder
	 */
	public static function findByIdForActor($id, $actorNode = null)
	{
		if (!$actorNode || !property_exists(get_called_class(), 'actorDataQuery') || empty(static::$actorDataQuery)) {
			return static::findById($id);
		}

		$nodeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);
		$query = "
			MATCH (objectNode:{$nodeLabel} {id:{id}})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
			WITH objectNode
		";
		$params = array(
			'id' => $id,
			'actorNodeId' => $actorNode->getId()
		);

		$finder = new Graph\NodeFinder(new Graph\Result(), static::$objectType, $query, $params);
		$finder->setNodeDataQuery(static::$actorDataQuery, $params)
			->setNoQueryByNodeId();

		return $finder;
	}

	/**
	 * @param array $idArray
	 * @return Graph\NodeFinder
	 */
	public static function findByIdArray($idArray)
	{
		$nodeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);
		$query = "
			MATCH (objectNode:{$nodeLabel})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
			WHERE objectNode.id IN {idArray}
			WITH objectNode
		";
		$params = array('idArray' => $idArray);

		$finder = new Graph\NodeFinder(new Graph\Result(), static::$objectType, $query, $params);
		$finder = $finder->setNodeDataQuery(static::$dataQuery)
			->setNoQueryByNodeId();

		return $finder;
	}

	/**
	 * @param array $idArray
	 * @param Node\Person $actorNode
	 * @return Graph\NodeFinder
	 */
	public static function findByIdArrayForActor($idArray, $actorNode = null)
	{
		if (!$actorNode || !property_exists(get_called_class(), 'actorDataQuery') || empty(static::$actorDataQuery)) {
			return static::findByIdArray($idArray);
		}

		$nodeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);
		$query = "
			MATCH (objectNode:{$nodeLabel})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
			WHERE objectNode.id IN {idArray}
			WITH objectNode
		";
		$params = array(
			'idArray' => $idArray,
			'actorNodeId' => $actorNode->getId()
		);

		$finder = new Graph\NodeFinder(new Graph\Result(), static::$objectType, $query, $params);
		$finder = $finder->setNodeDataQuery(static::$actorDataQuery, $params)
			->setNoQueryByNodeId();

		return $finder;
	}

	/**
  * @param string $url
  *
  * @throws \Exception
  */
 public static function findObjectAndAuthorAndProviderByContentUrl($url): bool|\Campus\Lib\Graph\Node
	{
		$url = Url::prepareForStorage($url);

		$query = "
			MATCH (objectNode:URL {url:{url}})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
				, (objectNode)<-[:POST|:CREATE|:PUBLISH]-(authorNode:PERSON)
			WITH
				objectNode, authorNode
				OPTIONAL MATCH (providerNode)<-[:HOST]-(hostNode)
			
			RETURN
			  objectNode, authorNode,
			  providerNode, hostNode
		";
		$params = array('url' => $url);

		$result = Graph\Client::cypherQuery($query, $params);
		if (!$result->count()) return false;

		$row = $result->current();
		/** @var Node[] $row */
		$node = $row['objectNode'];
		$node->setNodeRelatedData($row);

		return $node;
	}

	/**
  * @param $url
  */
 public static function findObjectAndAuthorAndProviderByCommentsUrl($url): bool|\Campus\Lib\Graph\Node
	{
		$query = "
            MATCH (objectNode:URL {commentsUrl:{url}})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
            RETURN objectNode
        ";
		$params = array('url' => $url);

		$result = Graph\Client::cypherQuery($query, $params);
		if (!$result->count()) return false;

		$row = $result->current();

		return $row['objectNode']->getProperty('url');
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function loadNodeRelatedData()
	{
		if (empty(static::$dataQuery)) throw new \Exception('For Node related data to be loaded static $dataQuery must be defined');

		$this->loadedRelatedData = true;

		$nodeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);

		$query = "
			MATCH (objectNode:{$nodeLabel})
			WHERE ID(objectNode) = {nodeId}
			WITH objectNode
		";
		$query .= static::$dataQuery;

		$params = array('nodeId' => $this->getId());

		$result = Graph\Client::cypherQuery($query, $params);
		if (!$result || !$result->count()) return false;

		$row = $result->current();
		$this->setNodeRelatedData($row);

		return true;
	}

	/**
	 * @param Neo4j\Query\Row $row
	 * @return $this
	 * @throws \Exception
	 */
	public function setNodeRelatedData(Neo4j\Query\Row $row)
	{

		if (!$row['objectNode'] instanceof Node) throw new \Exception('$row objectNode index must be an instance of Node...');
		if ($row['objectNode']->getId() !== $this->getId()) throw new \Exception('$row objectNode and $this node have mismatching ids...');

		foreach ($row as $key => $value) {
			if (!isset($value)) continue;
			if (!property_exists($this, $key)) continue;

			$this->{$key} = $value;
		}

		$this->loadedRelatedData = true;
		return $this;
	}

	protected function hasLoadedRelatedData()
	{
		return $this->loadedRelatedData;
	}

	public static function findObjectById($objectId, $objectType)
	{
		$nodeClass = StringUtils::getNodeClassFullyQualifiedNameFromObjectType($objectType);
		if (!$nodeClass || !method_exists($nodeClass, 'getById')) return false;

		/** @var \Campus\Lib\Graph\Node $objectNode */
		$objectNode = $nodeClass::getById($objectId);
		return $objectNode;
	}

	public static function findObjectByIdForActor($objectId, $objectType, Node\Person $actorNode)
	{
		$nodeClass = StringUtils::getNodeClassFullyQualifiedNameFromObjectType($objectType);
		if (!$nodeClass || !method_exists($nodeClass, 'getByIdForActor')) return false;

		/** @var \Campus\Lib\Graph\Node $objectNode */
		$objectNode = $nodeClass::getByIdForActor($objectId, $actorNode);
		return $objectNode;
	}

	/**
  * @return array
  */
 public static function findNodeIdsFromObjectIdArray(array $ids)
	{
		$nodeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);
		$query = "
			MATCH (node:{$nodeLabel})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
			WHERE node.id in {ids}
			RETURN ID(node) as nodeId
		";
		$params = ['ids' => $ids];

		$result = Graph\Client::cypherQuery($query, $params);

		$nodeIds = [];
		if (!$result->count()) return $nodeIds;

		foreach ($result as $row) {
			$nodeIds[] = $row['nodeId'];
		}

		return $nodeIds;
	}

	public function getProviderNode(): \Node\Organization|\Node\Group
	{
		$query = "
			MATCH (root)<-[:PROVIDE]-(provider)
			RETURN provider
		";

		$query = $this->prepareQuery($query);
		$result = Graph\Client::cypherQuery($query, array('id' => $this->getObjectId()));

		if (!$result || !$result->count()) return false;

		$providerNode = $result->current()['provider'];
		return $providerNode;
	}

	public function getSourceNode(): bool|\Node\Source
	{
		$query = "
			MATCH (root)-[:ORIGIN]->(sourceNode:SOURCE)
			RETURN sourceNode
		";

		$query = $this->prepareQuery($query);
		$result = Graph\Client::cypherQuery($query, array('id' => $this->getObjectId()));

		$objectNode = ($result && count($result))
			? $result->current()['sourceNode']
			: false;

		return $objectNode;
	}

	public function getTargetNode(): \Node\ImageCollection|\Node\Service|\Node\Task|\Node\MissionCollection
	{
		$query = "
			MATCH (root)-[:ADD]->(target)
			RETURN target
		";

		$query = $this->prepareQuery($query);
		$result = Graph\Client::cypherQuery($query, array('id' => $this->getObjectId()));

		if (!$result || !$result->count()) return false;

		$targetNode = $result->current()['target'];
		return $targetNode;
	}

	/**
	 * @param $objectId
	 * @param $objectType
	 * @return Node\Person
	 */
	public static function getAuthorNodeForObject($objectId, $objectType)
	{
		$nodeLabel = StringUtils::getLabelNameFromObjectType($objectType);

		$query = "
			MATCH (objectNode:{$nodeLabel} {id:{id}})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
				, (objectNode)<-[:CREATE|POST|PUBLISH|APPLY]-(authorNode:PERSON)
			RETURN authorNode
		";
		$params = array('id' => $objectId);

		$result = Graph\Client::cypherQuery($query, $params);

		$return = false;
		if ($result) {
			foreach ($result as $row) {
				$return = $row['authorNode'];
				break;
			}
		}

		return $return;
	}

	/**
	 * @return Node\Article[]
	 */
	public function getArticleNodes()
	{
		$query = "
            MATCH (root)<-[:ADD]-(objectNode:ARTICLE)
            RETURN objectNode
        ";

		$query = $this->prepareQuery($query);
		$result = Graph\Client::cypherQuery($query, array('id' => $this->getObjectId()));

		$return = array();
		foreach ($result as $node) {
			$objectNode = $node['objectNode'];
			$return[] = $objectNode;
		}

		return $return;
	}

	/**
	 * @return Node\Activity[]
	 */
	public function getActivityNodes()
	{
		$query = "
			MATCH (root)-[:ATTACH]->(objectNode:ACTIVITY)
			RETURN objectNode
		";

		$query = $this->prepareQuery($query);
		$result = Graph\Client::cypherQuery($query, array('id' => $this->getObjectId()));

		$return = array();
		foreach ($result as $node) {
			$objectNode = $node['objectNode'];
			$return[] = $objectNode;
		}

		return $return;
	}

	/**
	 * @return Node\Activity[]
	 */
	public function getProvidedActivityNodes()
	{
		$query = "
			MATCH (root)-[:PROVIDE]->(objectNode:ACTIVITY)
			RETURN objectNode
		";

		$query = $this->prepareQuery($query);
		$result = Graph\Client::cypherQuery($query, array('id' => $this->getObjectId()));

		$return = array();
		foreach ($result as $node) {
			$objectNode = $node['objectNode'];
			$return[] = $objectNode;
		}

		return $return;
	}

	/**
  * @return array
  */
 public function filterPersonIdArrayHasFavorited(array $personIds)
	{
		$personNodeIds = Node\Person::findNodeIdsFromObjectIdArray($personIds);

		$objectNodeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);
		$query = "
			MATCH (personNode:PERSON)-[:ORIGIN]->(:SOURCE {id:{dataSource}})
				, (objectNode:{$objectNodeLabel} {id:{id}})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
			WHERE ID(personNode) IN {personNodeIds}
			AND (personNode)-[:FAVORITE]->(objectNode)
			RETURN personNode.id as personId
		";

		$params = [
			'id' => $this->getObjectId(),
			'personNodeIds' => $personNodeIds
		];

		$result = Graph\Client::cypherQuery($query, $params);

		$filteredPersonIds = [];
		if (!$result || !$result->count()) return $filteredPersonIds;

		foreach ($result as $row) {
			$filteredPersonIds[] = $row['personId'];
		}

		return $filteredPersonIds;
	}

	public function updateProperty($property, $value)
	{
		$this->setProperty($property, $value)
			->setProperty(self::PROPERTY_UPDATED, time())
			->save();

		return true;
	}

	/**
	 * @param $query
	 * @return string
	 */
	public function prepareQuery($query)
	{
		if (static::$objectType) {
			$nodeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);
			$base = "
				MATCH (root:{$nodeLabel} {id:{id}})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
				WITH root
			";

			$query = $base . $query;
		}

		return $query;
	}
	/**
	 * @param $queryString
	 * @param array $params
	 * @return Neo4j\Query\ResultSet
	 * @deprecated Use the Client::cypherQuery directly
	 */
	public static function cypherQuery($queryString, $params = array())
	{
		return Client::cypherQuery($queryString, $params);
	}

	protected static function baseSearch(
		$searchQuery,
		$searchParams,
		$dataQuery,
		$queryString,
		array $idsToExclude = array(),
		array $fieldsToSearch = array('id', 'displayName'),
		$objectType = null
	) {
		$whereString = '';
		if (!empty($queryString)) {
			if (empty($fieldsToSearch)) throw new \Exception('If a query string is passed, the fields to search cannot be empty');

			$finalFields = new \ArrayIterator($fieldsToSearch);

			foreach ($finalFields as $field) {
				if (in_array($field, static::$propertiesToNormalize)) {
					$finalFields->append(Node::PROPERTY_PREFIX_NORMALIZED . $field);
					//$queryString = StringUtils::normalizeString($queryString);
				}

				$whereString .= empty($whereString)
					? "("
					: " OR ";
				$whereString .= "objectNode.{$field}=~{queryString}";
			}

			$whereString .= ") ";

			$queryString = preg_replace('/[^\p{Latin}\d ._%+-@]/u', '', $queryString);
			$queryString = preg_quote($queryString);
			$searchParams['queryString'] = "(?i).*{$queryString}.*";
		}

		if (!empty($idsToExclude)) {
			if (!empty($whereString)) $whereString .= "AND ";

			$whereString .= "NOT(objectNode.id IN {idsToExclude})";
			$searchParams['idsToExclude'] = $idsToExclude;
		}

		if (!empty($whereString)) {
			$whereString = is_bool(strpos((string) $searchQuery, 'WHERE'))
				? "WHERE " . $whereString
				: "AND " . $whereString;

			$searchQuery = explode('RETURN', (string) $searchQuery);
			$searchQuery = "{$searchQuery[0]} {$whereString} RETURN {$searchQuery[1]}";
		}

		$objectType = static::$objectType ? static::$objectType : $objectType;

		$finder = new Graph\NodeFinder(new Graph\Result(), $objectType, $searchQuery, $searchParams);
		$finder->setNodeDataQuery($dataQuery, $searchParams);

		return $finder;
	}

	protected static function basePersonNodeSearch(
		$searchQuery,
		$searchParams,
		$dataQuery,
		$queryString,
		array $idsToExclude = array(),
		array $fieldsToSearch = array('id', 'displayName')
	) {
		$whereString = '';
		if (!empty($queryString)) {
			if (empty($fieldsToSearch)) throw new \Exception('If a query string is passed, the fields to search cannot be empty');

			foreach ($fieldsToSearch as $field) {
				if (in_array($field, static::$propertiesToNormalize)) {
					$field = Node::PROPERTY_PREFIX_NORMALIZED . $field;
					$queryString = StringUtils::normalizeString($queryString);
				}

				$whereString .= empty($whereString)
					? "("
					: " OR ";
				$whereString .= "personNode.{$field}=~{queryString}";
			}
			$whereString .= ") ";

			$queryString = preg_replace('/[^\p{Latin}\d ._%+-@]/u', '', $queryString);
			$queryString = preg_quote($queryString);
			$searchParams['queryString'] = "(?i).*{$queryString}.*";
		}

		if (!empty($idsToExclude)) {
			if (!empty($whereString)) $whereString .= "AND ";

			$whereString .= "NOT(personNode.id IN {idsToExclude})";
			$searchParams['idsToExclude'] = $idsToExclude;
		}

		if (!empty($whereString)) {
			$whereString = is_bool(strpos((string) $searchQuery, 'WHERE'))
				? "WHERE " . $whereString
				: "AND " . $whereString;

			$searchQuery = explode('RETURN', (string) $searchQuery);
			$searchQuery = "{$searchQuery[0]} {$whereString} RETURN {$searchQuery[1]}";
		}

		$dataQuery = '';
		$finder = new Graph\NodeFinder(new Graph\Result(), static::$objectType, $searchQuery, $searchParams);
		$finder->setNodeDataQuery($dataQuery, $searchParams)->setNoQueryByNodeId();

		return $finder;
	}

	/**
	 * @param string $queryString
	 * @param array $idsToExclude
	 * @param array $fieldsToSearch
	 * @return NodeFinder
	 * @throws \Exception
	 */
	public static function search($queryString, $idsToExclude = array(), $fieldsToSearch = array('id', 'displayName'))
	{
		if (empty(static::$objectType) || empty(static::$dataQuery)) return false;

		$searchParams = array();
		$dataQuery = static::$dataQuery;

		$nodeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);
		$searchQuery = "
			MATCH (objectNode:{$nodeLabel})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
			RETURN ID(objectNode) AS id
		";

		$finder = Node::baseSearch($searchQuery, $searchParams, $dataQuery, $queryString, $idsToExclude, $fieldsToSearch, static::$objectType);
		return $finder;
	}

	/**
	 * @param string $queryString
	 * @param Node\Person $actorNode
	 * @param array $idsToExclude
	 * @param array $fieldsToSearch
	 * @return NodeFinder
	 * @throws \Exception
	 */
	public static function searchForActor($queryString, $actorNode = null, $idsToExclude = array(), $fieldsToSearch = array('id', 'displayName'))
	{
		if (!$actorNode || !property_exists(get_called_class(), 'actorDataQuery') || empty(static::$actorDataQuery)) {
			return self::search($queryString, $idsToExclude, $fieldsToSearch);
		}

		if (empty(static::$objectType) || empty(static::$dataQuery)) return false;

		$searchParams['actorNodeId'] = $actorNode->getId();
		$dataQuery = static::$actorDataQuery;

		$nodeLabel = StringUtils::getLabelNameFromObjectType(static::$objectType);
		$searchQuery = "
			MATCH (objectNode:{$nodeLabel})-[:ORIGIN]->(:SOURCE {id:{dataSource}})
			RETURN ID(objectNode) AS id
		";

		$finder = Node::baseSearch($searchQuery, $searchParams, $dataQuery, $queryString, $idsToExclude, $fieldsToSearch);
		return $finder;
	}

	/**
	 * @param Neo4j\Query\ResultSet $resultSet
	 * @return array
	 */
	public static function extractResultSetDataToArray(Neo4j\Query\ResultSet $resultSet)
	{
		$data = array();
		foreach ($resultSet as $row) {
			$rowData = self::extractRowDataToArray($row);
			$data[] = $rowData;
		}

		return $data;
	}

	/**
	 * @param Neo4j\Query\Row $row
	 * @return array
	 */
	public static function extractRowDataToArray(Neo4j\Query\Row $row)
	{
		$data = array();
		while ($row->valid()) {
			$value = $row->current() instanceof Neo4j\Query\Row
				? self::extractRowDataToArray($row->current())
				: $row->current();

			if (is_array($value) && count($value) === 1 && is_int(key($value))) $value = $value[0];

			$data[$row->key()] = $value;
			$row->next();
		}

		return $data;
	}

	/**
	 * @param array $properties
	 * @return static
	 */
	public function setProperties($properties)
	{
		//
		// Remove restricted properties for Pinnable objects
		//
		if ($this instanceof Node\Interfaces\Pinnable) {
			foreach ($properties as $property => $value) {
				if (!$this->isPinnedRestrictedPropertyName($property)) continue;

				unset($properties[$property]);
			}
		}

		return parent::setProperties($properties);
	}

	/**
  * @param string $property
  * @return static
  */
 public function setProperty($property, mixed $value)
	{
		//
		// Ignore property if restricted for Pinnable objects
		//
		if ($this instanceof Node\Interfaces\Pinnable) {
			if ($this->isPinnedRestrictedPropertyName($property)) return $this;
		}

		return parent::setProperty($property, $value);
	}

	/**
	 * @return array
	 */
	public function getProperties()
	{
		$properties = parent::getProperties();

		//
		// Remove restricted properties for Pinnable objects
		//
		if ($this instanceof Node\Interfaces\Pinnable) {
			foreach ($properties as $property => $value) {
				if (!$this->isPinnedRestrictedPropertyName($property)) continue;

				unset($properties[$property]);
			}
		}

		return $properties;
	}

	/**
	 * @param string $property
	 * @return mixed|void
	 */
	public function getProperty($property)
	{
		//
		// Return null if restricted for Pinnable objects
		//
		if ($this instanceof Node\Interfaces\Pinnable) {
			if ($this->isPinnedRestrictedPropertyName($property)) return null;
		}

		return parent::getProperty($property);
	}

	/**
	 * @param string $query
	 * @param string $condition
	 * @param string $keyword
	 * @return string
	 * @throws \Exception
	 */
	protected function addConditionToQueryBeforeKeyword($query, $condition, $keyword)
	{
		if (!strpos($query, $keyword)) throw new \Exception("The keyword {$keyword} was not found in the provided query!");

		// In case we get a condition with the `WHERE` keyword
		$condition = str_replace('WHERE', '', $condition);

		list($firstPart, $secondPart) = explode($keyword, $query, 2);

		// If there are WITH in the middle, we just want the last part
		if (str_contains($firstPart, 'WITH')) {
			$beforeWith = explode('WITH', $firstPart);

			$firstPart = array_pop($beforeWith);
			$beforeWith = implode('WITH', $beforeWith);
		}

		if (str_contains($firstPart, 'WHERE')) {
			list($firstPart, $existingWhere) = explode('WHERE', $firstPart, 2);
			$condition = "{$existingWhere} AND ({$condition})";
		}

		$condition = "WHERE {$condition}";

		$query = "{$firstPart} {$condition} {$keyword} {$secondPart}";
		if (isset($beforeWith)) $query = "{$beforeWith} WITH {$query}";

		return $query;
	}

	/**
	 * @param array $properties
	 * @param string $key
	 * @param string $parentObjectType
	 * @param string $dehydrated
	 * @param Node\Person $actorNode
	 *
	 * @return ActivityStreams\Object
	 */
	protected function propertiesArrayToActivityStreams($properties, $key = null, $parentObjectType = false, $dehydrated = false, $actorNode = null)
	{
		if (!empty($properties['objectType'])) {
			if ($properties['objectType'] === 'dummy') $properties['objectType'] = 'note';

			$objectClassName = explode('-', (string) $properties['objectType']);
			$objectClassName = array_map('ucwords', $objectClassName);
			$objectClassName = implode('', $objectClassName);
			$objectClassName = '\\Campus\\ActivityStreams\\Object\\' . $objectClassName;

			/** @var \Campus\ActivityStreams\Object $object */
			$object = new $objectClassName();
		} elseif (!empty($properties['url'])) {
			$object = MediaLinkFactory::getForObjectTypeAndPropertyName($properties['url'], $parentObjectType, $key, @$properties['blur']);
			unset($properties['url']);
		} else {
			$object = array();
		}

		if ($dehydrated) $object = ActivityStreams\Dehydrator::trimNonScalar($object);

		foreach ($properties as $key => $property) {
			if ($dehydrated) {
				$objectType = is_object($object) && property_exists($object, 'objectType')
					? $object->objectType
					: null;

				if (!ActivityStreams\Dehydrator::shouldPreserveKey($key, $property, $objectType)) {
					unset($properties[$key]);
					continue;
				}
			}

			if (!is_scalar($property)) {
				$parentObjectType = ($object instanceof ActivityStreams\Object) ? $object->getObjectType() : false;
				$property = $this->propertiesArrayToActivityStreams($property, $key, $parentObjectType, $dehydrated, $actorNode);
			}

			if (!is_array($object)) {
				if ($object instanceof ActivityStreams\PropertyContainer && !property_exists($object, $key)) {
					$object->setCustomProperty($key, $property);
				} else {
					$object->{$key} = $property;
				}
			} else {
				$object[$key] = $property;
			}
		}

		return $object;
	}
	/**
	 * @param array $properties
	 * @param string $key
	 * @param string $parentObjectType
	 * @param string $dehydrated
	 * @param Node\Person $actorNode
	 *
	 * @return ActivityStreams\Object
	 */
	protected function propertiesArrayToActivityStreamsWarmup($properties, $key = null, $parentObjectType = false, $dehydrated = false, $actorNode = null)
	{
		if (!empty($properties['objectType'])) {
			if ($properties['objectType'] === 'dummy') $properties['objectType'] = 'note';

			$objectClassName = explode('-', (string) $properties['objectType']);
			$objectClassName = array_map('ucwords', $objectClassName);
			$objectClassName = implode('', $objectClassName);
			$objectClassName = '\\Campus\\ActivityStreams\\Object\\' . $objectClassName;

			/** @var \Campus\ActivityStreams\Object $object */
			$object = new $objectClassName();
		} elseif (!empty($properties['url'])) {
			$object = MediaLinkFactory::getForObjectTypeAndPropertyName($properties['url'], $parentObjectType, $key, @$properties['blur']);
			unset($properties['url']);
		} else {
			$object = array();
		}

		if ($dehydrated) $object = ActivityStreams\Dehydrator::trimNonScalar($object);

		return $object;
	}

	protected function expand(array $source)
	{
		$data = array();

		foreach ($source as $key => $value) {
			$keys = explode("_", $key);
			$fKey = $keys[0];

			if (count($keys) === 1) {
				$data[$fKey] = $value;
			} else {
				if (!array_key_exists($fKey, $data)) $data[$fKey] = [];

				array_shift($keys);
				$recursion = $this->expand(array(implode("_", $keys) => $value));

				$data[$fKey] = $this->array_merge_all($data[$fKey], $recursion);
			}
		}

		return $data;
	}

	protected function array_merge_all()
	{
		$output = array();
		foreach (func_get_args() as $array) {
			if (!is_array($array)) continue;

			foreach ($array as $key => $value) {
				$output[$key] = isset($output[$key])
					? $this->array_merge_all($output[$key], $value)
					: $value;
			}
		}
		return $output;
	}

	public static function flatten(array $source, array &$destiny = array(), $namespace = null)
	{
		foreach ($source as $key => $value) {
			//
			// Be sure to remove '@' characters from non standard properties
			//
			if (str_starts_with($key, '@')) $key = substr($key, 1);
			if ($namespace) $key = $namespace . '_' . $key;
			if (is_scalar($value)) $destiny[$key] = $value;
			if (is_array($value)) self::flatten($value, $destiny, $key);

			if (is_object($value)) {
				$value = (array) $value;
				self::flatten($value, $destiny, $key);
			}
		}

		return $destiny;
	}

	public static function normalizeProperties($properties, $propertiesToNormalize)
	{
		foreach ($propertiesToNormalize as $property) {
			if (isset($properties[$property])) {
				$normalized = StringUtils::normalizeString($properties[$property]);
				$properties[Node::PROPERTY_PREFIX_NORMALIZED . $property] = $normalized;
			}
		}

		return $properties;
	}

	/**
	 * @param string $labelName
	 * @return bool
	 */
	public function hasLabel($labelName)
	{
		$hasLabel = false;

		/** @var Neo4j\Label $label */
		foreach ($this->getLabels() as $label) {
			if ($label->getName() === $labelName) {
				$hasLabel = true;
				break;
			}
		}

		return $hasLabel;
	}

	/**
	 * @return bool
	 */
	public function setActive()
	{
		$this->removeLabels([Client::getLabelInstance(strtoupper(self::PROPERTY_INACTIVE))]);
		$this->addLabels([Client::getLabelInstance(strtoupper(self::PROPERTY_ACTIVE))]);

		$this->setProperty(self::PROPERTY_ACTIVE, true);
		$this->save();

		return true;
	}

	/**
	 * @return bool
	 */
	public function setInactive()
	{
		$this->removeLabels([Client::getLabelInstance(strtoupper(self::PROPERTY_ACTIVE))]);
		$this->addLabels([Client::getLabelInstance(strtoupper(self::PROPERTY_INACTIVE))]);

		$this->setProperty(self::PROPERTY_ACTIVE, false);
		$this->save();

		return true;
	}

	/**
	 * @return bool
	 */
	public function checkIsActive()
	{
		return (bool) $this->getProperty(self::PROPERTY_ACTIVE);
	}

	/**
	 * @return bool
	 */
	public function checkIsPlatformAdmin()
	{
		foreach ($this->getLabels() as $label) {
			//todo move to constant
			if ($label->getName() === 'ADMIN') return true;
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public function checkIsInstitution()
	{
		foreach ($this->getLabels() as $label) {
			//todo move to constant
			if ($label->getName() === 'INSTITUTION') return true;
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public function checkContextOgPrivacy()
	{
		return ($this->getPrivacy() === iPrivacy::VALUE_PRIVACY_PUBLIC);
	}

	/**
	 * @param $date
	 * @param $dateFormat // y-m-d  // hours // minutes // days // yyyy-mm-dd~h:m:s
	 * @return int //unix timestamp
	 */
	public static function getTimeStamp($date, $dateFormat)
	{
		$date = str_replace("~", " ", (string) $date);
		switch ($dateFormat) {
			case "y-m-d~h:m:s":
				$date = new Datetime($date);
				$timestamp = $date->getTimestamp();
				break;
			case "y-m-d":
				$date = new Datetime($date);
				$timestamp = $date->getTimestamp();
				break;
			case "hours" || "minutes" || "days":
				$timestamp = strtotime("-{$date} $dateFormat");
				break;
			default:
				throw new \Exception('Type defined for data is invalid: ' . $dateFormat . 'valid formats: y-m-d  // hours // minutes // days // yyyy-mm-dd~h:m:s ');
		}
		return $timestamp;
	}

	/**
	 * @param $label
	 * @param $since
	 * @param $until
	 * @param $gps
	 * @return Neo4j\Query\ResultSet
	 * @throws \Campus\Exceptions\Neo4j\Neo4jConnectionException
	 * @throws \Campus\Exceptions\Neo4j\Neo4jCypherQueryException
	 * @throws \Exception
	 */
	public function getTotalByLabel($label, $since, $until, $gps = false)
	{
		if (Config::getPlatformId() === 'gps' && $gps) {
			$gps = ":GPS";
		} else {
			$gps = "";
		}



		$query = " MATCH (objectNode:{$label}{$gps})- [:ORIGIN] -> (:SOURCE {id:{dataSource}}) ";

		if ($since && $until) {
			$query .= "WHERE objectNode.published <= $until AND objectNode.published  >= $since ";
		} elseif ($since) {
			$query .= "WHERE objectNode.published  >= $since";
		} elseif ($until) {
			$query .= "WHERE objectNode.published <= $until";
		}

		$query .= " RETURN count(objectNode)";


		$result = Graph\Client::cypherQuery($query);
		return $result;
	}

	/**
	 * @param string $nodeLabel
	 * @param $relation
	 * @param null $nodeLabelRelated
	 * @param null $since
	 * @param null $until
	 * @param bool $gps
	 * @return Neo4j\Query\ResultSet
	 * @throws \Campus\Exceptions\Neo4j\Neo4jConnectionException
	 * @throws \Campus\Exceptions\Neo4j\Neo4jCypherQueryException
	 */
	public static function countRelation($relation, $nodeLabel = "", $nodeLabelRelated = null, $since = null, $until = null, $gps = false)
	{

		if ($gps && Config::getPlatformId() === 'gps') {
			if ($nodeLabel === "PERSON") {
				$nodeLabel = "PERSON:GPS";
			} elseif ($nodeLabelRelated === "PERSON") {
				$nodeLabelRelated = "PERSON:GPS";
			}
		}

		$query = "MATCH (:{$nodeLabel}) - [relationType:{$relation}] ";

		$query .= $nodeLabelRelated !== null ? " - (:$nodeLabelRelated) " : " - ()";



		$query .= " - [:ORIGIN] -> (:SOURCE {id:{dataSource}}) ";

		if ($since && $until) {
			$query .= "WHERE relationType.published <= $until AND relationType.published  >= $since ";
		} elseif ($since) {
			$query .= "WHERE relationType.published  >= $since";
		} elseif ($until) {
			$query .= "WHERE relationType.published <= $until";
		}

		$query .= " RETURN count(relationType)";

		$result = Graph\Client::cypherQuery($query);

		return $result;
	}

	/**
	 * @param Node\Person $actorNode
	 * @return bool
	 */
	public abstract function checkCanActorManage(Node\Person $actorNode);

	/**
	 * @return bool
	 */
	public abstract function checkCanPublicActorGet();
}

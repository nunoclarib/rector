<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace RectorPrefix20220407\Symfony\Component\Config\Definition;

use RectorPrefix20220407\Symfony\Component\Config\Definition\Exception\DuplicateKeyException;
use RectorPrefix20220407\Symfony\Component\Config\Definition\Exception\Exception;
use RectorPrefix20220407\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use RectorPrefix20220407\Symfony\Component\Config\Definition\Exception\UnsetKeyException;
/**
 * Represents a prototyped Array node in the config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PrototypedArrayNode extends \RectorPrefix20220407\Symfony\Component\Config\Definition\ArrayNode
{
    protected $prototype;
    protected $keyAttribute;
    protected $removeKeyAttribute = \false;
    protected $minNumberOfElements = 0;
    protected $defaultValue = [];
    protected $defaultChildren;
    /**
     * @var NodeInterface[] An array of the prototypes of the simplified value children
     */
    private $valuePrototypes = [];
    /**
     * Sets the minimum number of elements that a prototype based node must
     * contain. By default this is zero, meaning no elements.
     */
    public function setMinNumberOfElements(int $number)
    {
        $this->minNumberOfElements = $number;
    }
    /**
     * Sets the attribute which value is to be used as key.
     *
     * This is useful when you have an indexed array that should be an
     * associative array. You can select an item from within the array
     * to be the key of the particular item. For example, if "id" is the
     * "key", then:
     *
     *     [
     *         ['id' => 'my_name', 'foo' => 'bar'],
     *     ];
     *
     *  becomes
     *
     *      [
     *          'my_name' => ['foo' => 'bar'],
     *      ];
     *
     * If you'd like "'id' => 'my_name'" to still be present in the resulting
     * array, then you can set the second argument of this method to false.
     *
     * @param string $attribute The name of the attribute which value is to be used as a key
     * @param bool   $remove    Whether or not to remove the key
     */
    public function setKeyAttribute(string $attribute, bool $remove = \true)
    {
        $this->keyAttribute = $attribute;
        $this->removeKeyAttribute = $remove;
    }
    /**
     * Retrieves the name of the attribute which value should be used as key.
     */
    public function getKeyAttribute() : ?string
    {
        return $this->keyAttribute;
    }
    /**
     * Sets the default value of this node.
     */
    public function setDefaultValue(array $value)
    {
        $this->defaultValue = $value;
    }
    /**
     * {@inheritdoc}
     */
    public function hasDefaultValue() : bool
    {
        return \true;
    }
    /**
     * Adds default children when none are set.
     *
     * @param int|string|array|null $children The number of children|The child name|The children names to be added
     */
    public function setAddChildrenIfNoneSet($children = ['defaults'])
    {
        if (null === $children) {
            $this->defaultChildren = ['defaults'];
        } else {
            $this->defaultChildren = \is_int($children) && $children > 0 ? \range(1, $children) : (array) $children;
        }
    }
    /**
     * {@inheritdoc}
     *
     * The default value could be either explicited or derived from the prototype
     * default value.
     * @return mixed
     */
    public function getDefaultValue()
    {
        if (null !== $this->defaultChildren) {
            $default = $this->prototype->hasDefaultValue() ? $this->prototype->getDefaultValue() : [];
            $defaults = [];
            foreach (\array_values($this->defaultChildren) as $i => $name) {
                $defaults[null === $this->keyAttribute ? $i : $name] = $default;
            }
            return $defaults;
        }
        return $this->defaultValue;
    }
    /**
     * Sets the node prototype.
     */
    public function setPrototype(\RectorPrefix20220407\Symfony\Component\Config\Definition\PrototypeNodeInterface $node)
    {
        $this->prototype = $node;
    }
    /**
     * Retrieves the prototype.
     */
    public function getPrototype() : \RectorPrefix20220407\Symfony\Component\Config\Definition\PrototypeNodeInterface
    {
        return $this->prototype;
    }
    /**
     * Disable adding concrete children for prototyped nodes.
     *
     * @throws Exception
     */
    public function addChild(\RectorPrefix20220407\Symfony\Component\Config\Definition\NodeInterface $node)
    {
        throw new \RectorPrefix20220407\Symfony\Component\Config\Definition\Exception\Exception('A prototyped array node cannot have concrete children.');
    }
    /**
     * {@inheritdoc}
     * @param mixed $value
     * @return mixed
     */
    protected function finalizeValue($value)
    {
        if (\false === $value) {
            throw new \RectorPrefix20220407\Symfony\Component\Config\Definition\Exception\UnsetKeyException(\sprintf('Unsetting key for path "%s", value: %s.', $this->getPath(), \json_encode($value)));
        }
        foreach ($value as $k => $v) {
            $prototype = $this->getPrototypeForChild($k);
            try {
                $value[$k] = $prototype->finalize($v);
            } catch (\RectorPrefix20220407\Symfony\Component\Config\Definition\Exception\UnsetKeyException $e) {
                unset($value[$k]);
            }
        }
        if (\count($value) < $this->minNumberOfElements) {
            $ex = new \RectorPrefix20220407\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException(\sprintf('The path "%s" should have at least %d element(s) defined.', $this->getPath(), $this->minNumberOfElements));
            $ex->setPath($this->getPath());
            throw $ex;
        }
        return $value;
    }
    /**
     * {@inheritdoc}
     *
     * @throws DuplicateKeyException
     * @param mixed $value
     * @return mixed
     */
    protected function normalizeValue($value)
    {
        if (\false === $value) {
            return $value;
        }
        $value = $this->remapXml($value);
        $arrayIsList = function (array $array) : bool {
            if (\function_exists('RectorPrefix20220407\\array_is_list')) {
                return array_is_list($array);
            }
            if ($array === []) {
                return \true;
            }
            $current_key = 0;
            foreach ($array as $key => $noop) {
                if ($key !== $current_key) {
                    return \false;
                }
                ++$current_key;
            }
            return \true;
        };
        $isList = $arrayIsList($value);
        $normalized = [];
        foreach ($value as $k => $v) {
            if (null !== $this->keyAttribute && \is_array($v)) {
                if (!isset($v[$this->keyAttribute]) && \is_int($k) && $isList) {
                    $ex = new \RectorPrefix20220407\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException(\sprintf('The attribute "%s" must be set for path "%s".', $this->keyAttribute, $this->getPath()));
                    $ex->setPath($this->getPath());
                    throw $ex;
                } elseif (isset($v[$this->keyAttribute])) {
                    $k = $v[$this->keyAttribute];
                    if (\is_float($k)) {
                        $k = \var_export($k, \true);
                    }
                    // remove the key attribute when required
                    if ($this->removeKeyAttribute) {
                        unset($v[$this->keyAttribute]);
                    }
                    // if only "value" is left
                    if (\array_keys($v) === ['value']) {
                        $v = $v['value'];
                        if ($this->prototype instanceof \RectorPrefix20220407\Symfony\Component\Config\Definition\ArrayNode && ($children = $this->prototype->getChildren()) && \array_key_exists('value', $children)) {
                            $valuePrototype = \current($this->valuePrototypes) ?: clone $children['value'];
                            $valuePrototype->parent = $this;
                            $originalClosures = $this->prototype->normalizationClosures;
                            if (\is_array($originalClosures)) {
                                $valuePrototypeClosures = $valuePrototype->normalizationClosures;
                                $valuePrototype->normalizationClosures = \is_array($valuePrototypeClosures) ? \array_merge($originalClosures, $valuePrototypeClosures) : $originalClosures;
                            }
                            $this->valuePrototypes[$k] = $valuePrototype;
                        }
                    }
                }
                if (\array_key_exists($k, $normalized)) {
                    $ex = new \RectorPrefix20220407\Symfony\Component\Config\Definition\Exception\DuplicateKeyException(\sprintf('Duplicate key "%s" for path "%s".', $k, $this->getPath()));
                    $ex->setPath($this->getPath());
                    throw $ex;
                }
            }
            $prototype = $this->getPrototypeForChild($k);
            if (null !== $this->keyAttribute || !$isList) {
                $normalized[$k] = $prototype->normalize($v);
            } else {
                $normalized[] = $prototype->normalize($v);
            }
        }
        return $normalized;
    }
    /**
     * {@inheritdoc}
     * @param mixed $leftSide
     * @param mixed $rightSide
     * @return mixed
     */
    protected function mergeValues($leftSide, $rightSide)
    {
        if (\false === $rightSide) {
            // if this is still false after the last config has been merged the
            // finalization pass will take care of removing this key entirely
            return \false;
        }
        if (\false === $leftSide || !$this->performDeepMerging) {
            return $rightSide;
        }
        $arrayIsList = function (array $array) : bool {
            if (\function_exists('RectorPrefix20220407\\array_is_list')) {
                return array_is_list($array);
            }
            if ($array === []) {
                return \true;
            }
            $current_key = 0;
            foreach ($array as $key => $noop) {
                if ($key !== $current_key) {
                    return \false;
                }
                ++$current_key;
            }
            return \true;
        };
        $isList = $arrayIsList($rightSide);
        foreach ($rightSide as $k => $v) {
            // prototype, and key is irrelevant there are no named keys, append the element
            if (null === $this->keyAttribute && $isList) {
                $leftSide[] = $v;
                continue;
            }
            // no conflict
            if (!\array_key_exists($k, $leftSide)) {
                if (!$this->allowNewKeys) {
                    $ex = new \RectorPrefix20220407\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException(\sprintf('You are not allowed to define new elements for path "%s". Please define all elements for this path in one config file.', $this->getPath()));
                    $ex->setPath($this->getPath());
                    throw $ex;
                }
                $leftSide[$k] = $v;
                continue;
            }
            $prototype = $this->getPrototypeForChild($k);
            $leftSide[$k] = $prototype->merge($leftSide[$k], $v);
        }
        return $leftSide;
    }
    /**
     * Returns a prototype for the child node that is associated to $key in the value array.
     * For general child nodes, this will be $this->prototype.
     * But if $this->removeKeyAttribute is true and there are only two keys in the child node:
     * one is same as this->keyAttribute and the other is 'value', then the prototype will be different.
     *
     * For example, assume $this->keyAttribute is 'name' and the value array is as follows:
     *
     *     [
     *         [
     *             'name' => 'name001',
     *             'value' => 'value001'
     *         ]
     *     ]
     *
     * Now, the key is 0 and the child node is:
     *
     *     [
     *        'name' => 'name001',
     *        'value' => 'value001'
     *     ]
     *
     * When normalizing the value array, the 'name' element will removed from the child node
     * and its value becomes the new key of the child node:
     *
     *     [
     *         'name001' => ['value' => 'value001']
     *     ]
     *
     * Now only 'value' element is left in the child node which can be further simplified into a string:
     *
     *     ['name001' => 'value001']
     *
     * Now, the key becomes 'name001' and the child node becomes 'value001' and
     * the prototype of child node 'name001' should be a ScalarNode instead of an ArrayNode instance.
     * @return mixed
     */
    private function getPrototypeForChild(string $key)
    {
        $prototype = $this->valuePrototypes[$key] ?? $this->prototype;
        $prototype->setName($key);
        return $prototype;
    }
}

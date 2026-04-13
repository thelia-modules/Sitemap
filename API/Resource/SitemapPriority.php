<?php

namespace Sitemap\API\Resource;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Sitemap\Model\Map\SitemapPriorityTableMap;
use Sitemap\Model\SitemapPriorityQuery;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Thelia\Api\Resource\Product as ProductResource;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\ResourceAddonTrait;
use Thelia\Model\Product;

class SitemapPriority implements ResourceAddonInterface
{
    use ResourceAddonTrait;

    #[Groups([ProductResource::GROUP_ADMIN_READ, ProductResource::GROUP_ADMIN_WRITE])]
    protected float $value;

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * No JOIN needed: sitemap_priority uses a polymorphic relation (object_id/object_type),
     * not a direct Propel FK, so we load data in buildFromModel instead.
     */
    public function buildFromArray(array $data, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        if (isset($data['value'])) {
            $this->setValue($data['value']);
        }

        return $this;
    }

    public static function extendQuery(ModelCriteria $query, Operation $operation = null, array $context = []): void
    {
    }

    public function buildFromModel(ActiveRecordInterface|Product $activeRecord, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        $sitemapModel = SitemapPriorityQuery::create()
            ->filterBySource(static::getObjectType())
            ->filterBySourceId($activeRecord->getId())
            ->findOne();

        if (null === $sitemapModel) {
            return $this;
        }

        $this->setValue($sitemapModel->getValue());

        return $this;
    }

    /**
     * @throws PropelException
     */
    public function doSave(ActiveRecordInterface|Product $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        $sitemapModel = SitemapPriorityQuery::create()
            ->filterBySourceId($activeRecord->getId())
            ->filterBySource(static::getObjectType())
            ->findOneOrCreate();

        $sitemapModel->setSourceId($activeRecord->getId());
        $sitemapModel->setSource(static::getObjectType());
        $sitemapModel->setValue($this->getValue());
        $sitemapModel->save();
    }

    #[Ignore]
    public static function getResourceParent(): string
    {
        return \Thelia\Api\Resource\Product::class;
    }

    #[Ignore]
    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new SitemapPriorityTableMap();
    }

    #[Ignore]
    public static function getObjectType(): string
    {
        return strtolower((new \ReflectionClass(static::getResourceParent()))->getShortName());
    }
}

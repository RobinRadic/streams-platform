<?php

namespace Anomaly\Streams\Platform\Entry;

use Carbon\Carbon;
use Laravel\Scout\Searchable;
use Laravel\Scout\ModelObserver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Anomaly\Streams\Platform\Model\EloquentModel;
use Anomaly\Streams\Platform\Stream\StreamBuilder;
use Anomaly\Streams\Platform\Model\Traits\Versionable;
use Anomaly\Streams\Platform\Entry\Contract\EntryInterface;
use Anomaly\Streams\Platform\Stream\Contract\StreamInterface;
use Anomaly\Streams\Platform\Presenter\Contract\PresentableInterface;

/**
 * Class EntryModel
 *
 * @link   http://pyrocms.com/
 * @author PyroCMS, Inc. <support@pyrocms.com>
 * @author Ryan Thompson <ryan@pyrocms.com>
 */
class EntryModel extends EloquentModel implements EntryInterface, PresentableInterface
{
    use Searchable;
    use Versionable;
    use SoftDeletes;

    /**
     * The stream definition.
     *
     * @var array
     */
    protected $stream = [];

    /**
     * Enable timestamps.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The searchable flag.
     *
     * @var boolean
     */
    protected $searchable = false;

    /**
     * The entry relationships by field slug.
     *
     * @var array
     */
    protected $relationships = [];

    /**
     * Hide these from toArray.
     *
     * @var array
     */
    protected $hidden = [
        'stream',
    ];

    /**
     * Date casted attributes.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        $instance = new static;

        $class     = get_class($instance);
        $events    = $instance->getObservableEvents();
        $observer  = substr($class, 0, -5) . 'Observer';
        $observing = class_exists($observer);

        if ($events && $observing) {
            self::observe(app($observer));
        }

        if (!$instance->stream()->searchable) {
            ModelObserver::disableSyncingFor(get_class(new static));
        }

        if ($events && !$observing) {
            self::observe(EntryObserver::class);
        }
    }

    /**
     * Get a field value.
     *
     * @param        $fieldSlug
     * @param  null $locale
     * @return mixed
     */
    public function getFieldValue($fieldSlug, $locale = null)
    {
        $field = $this->stream()->fields->{$fieldSlug};

        $type = $field->type();

        $modifier = $type->getModifier();

        $type->setEntry($this);

        $value = parent::getAttributeValue($fieldSlug);

        if ($field->translatable) {
            $value = $value[$this->locale($locale)];
        }

        $value = $modifier->restore($value);

        $type->setValue($value);

        return $value;
    }

    /**
     * Set a field value.
     *
     * @param        $fieldSlug
     * @param        $value
     * @param  null $locale
     * @return $this
     */
    public function setFieldValue($fieldSlug, $value, $locale = null)
    {
        $assignment = $this->getAssignment($fieldSlug);

        $type = $assignment->getFieldType($this);

        $type->setEntry($this);

        $modifier = $type->getModifier();

        $key = $type->getColumnName();

        if ($assignment->isTranslatable()) {
            $key = $key . '->' . ($locale ?: app()->getLocale());
        }

        return parent::setAttribute($key, $modifier->modify($value));
    }

    /**
     * Get a raw unmodified attribute.
     *
     * @param             $key
     * @param  bool $process
     * @return mixed|null
     */
    public function getRawAttribute($key, $process = true)
    {
        if (!$process) {
            return $this->getAttributeFromArray($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Set a raw unmodified attribute.
     *
     * @param $key
     * @param $value
     * @return $this
     */
    public function setRawAttribute($key, $value)
    {
        parent::setAttribute($key, $value);

        return $this;
    }

    /**
     * Set a given attribute on the model.
     * Override the behavior here to give
     * the field types a chance to modify things.
     *
     * @param  string $key
     * @param  mixed $value
     * @param  string|null $locale
     * @return EntryModel|EloquentModel
     */
    public function setAttribute($key, $value, $locale = null)
    {
        if ($this->isTranslatedAttribute($key) && !$this->hasSetMutator($key) && $this->getFieldType($key)) {
            return $this->setFieldValue($key, $value, $locale);
        }

        return parent::setAttribute($key, $value, $locale);
    }

    /**
     * Get a given attribute on the model.
     * Override the behavior here to give
     * the field types a chance to modify things.
     *
     * @param  string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        // Check if it's a relationship first.
        if (in_array($key, ['created_by', 'updated_by']) || $key == 'roles') {
            return parent::getAttribute(camel_case($key));
        }

        if (!$this->hasGetMutator($key) && $this->stream()->fields->has($key)) {
            return $this->getFieldValue($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Return the stream.
     *
     * @return StreamInterface
     */
    public function stream()
    {
        return $this->remember($this->getTable(), function () {

            $this->stream['model'] = clone ($this);

            return StreamBuilder::build($this->stream);
        });
    }

    /**
     * Return the last modified datetime.
     *
     * @return Carbon
     */
    public function lastModified()
    {
        return $this->updated_at ?: $this->created_at;
    }

    /**
     * Return the created by relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Return the updated by relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Fire field type events.
     *
     * @param       $trigger
     * @param array $payload
     */
    public function fireFieldTypeEvents($trigger, $payload = [])
    {
        // foreach ($this->stream()->fields as $field) {

        //     $fieldType = $field->type();
        //     dd($fieldType);
        //     $fieldType->setValue($this->getRawAttribute($field->slug));

        //     $fieldType->setEntry($this);

        //     $payload['entry']     = $this;
        //     $payload['fieldType'] = $fieldType;

        //     $fieldType->fire($trigger, $payload);
        // }
    }

    /**
     * @param  array $items
     * @return EntryCollection
     */
    public function newCollection(array $items = [])
    {
        $collection = substr(get_class($this), 0, -5) . 'Collection';

        if (class_exists($collection)) {
            return new $collection($items);
        }

        return new EntryCollection($items);
    }

    /**
     * Return the entry presenter.
     *
     * This is against standards but required
     * by the presentable interface.
     *
     * @return EntryPresenter
     */
    public function newPresenter()
    {
        $presenter = substr(get_class($this), 0, -5) . 'Presenter';

        if (class_exists($presenter)) {
            return app()->make($presenter, ['object' => $this]);
        }

        return new EntryPresenter($this);
    }

    // /**
    //  * Return a model route.
    //  *
    //  * @param       $route The route name you would like to return a URL for (i.e. "view" or "delete")
    //  * @param array $parameters
    //  * @return string
    //  */
    // public function route($route, array $parameters = [])
    // {
    //     $router = $this->getRouter();

    //     return $router->make($route, $parameters);
    // }

    // /**
    //  * Return a new router instance.
    //  *
    //  * @return EntryRouter
    //  */
    // public function newRouter()
    // {
    //     return app()->make($this->getRouterName(), ['entry' => $this]);
    // }

    // /**
    //  * Get the router.
    //  *
    //  * @return EntryRouter
    //  */
    // public function getRouter()
    // {
    //     if (isset($this->cache['router'])) {
    //         return $this->cache['router'];
    //     }

    //     return $this->cache['router'] = $this->newRouter();
    // }

    /**
     * Get the router name.
     *
     * @return string
     */
    public function getRouterName()
    {
        $router = substr(get_class($this), 0, -5) . 'Router';

        return class_exists($router) ? $router : EntryRouter::class;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        $builder = $this->getQueryBuilderName();

        return new $builder($query);
    }

    /**
     * Get the router name.
     *
     * @return string
     */
    public function getQueryBuilderName()
    {
        $builder = substr(get_class($this), 0, -5) . 'QueryBuilder';

        return class_exists($builder) ? $builder : EntryQueryBuilder::class;
    }

    /**
     * Get the criteria class.
     *
     * @return string
     */
    public function getCriteriaName()
    {
        $criteria = substr(get_class($this), 0, -5) . 'Criteria';

        return class_exists($criteria) ? $criteria : EntryCriteria::class;
    }

    /**
     * Override the __get method.
     *
     * @param  string $key
     * @return EntryPresenter|mixed
     */
    public function __get($key)
    {
        if ($key === 'decorate') {
            return $this->newPresenter();
        }

        if ($key === 'stream') {
            return $this->stream();
        }

        return parent::__get($key); // TODO: Change the autogenerated stub
    }
}

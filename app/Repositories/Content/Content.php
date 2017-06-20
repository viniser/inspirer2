<?php
/**
 * Content.php
 *
 * @copyright Chongyi <xpz3847878@163.com>
 * @link      https://insp.top
 */

namespace App\Repositories\Content;

use App\Contracts\ContentStructure;
use App\Events\ContentPublished;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\OperationRejectedException;
use App\Repositories\Content\ContentNodePivot\TreeNode;
use App\Repositories\Traits\ContentMetaSetterAndGetterTrait;
use App\Repositories\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Content
 *
 * 内容模型
 *
 * @property int           $id
 * @property string        $title
 * @property string        $keywords
 * @property string        $description
 * @property Content|Model $entity
 * @property Carbon        $created_at
 * @property Carbon        $updated_at
 * @property Carbon        $published_at
 * @property User          $author
 * @property string        $author_name
 * @property int           $author_id
 *
 * @package App\Repositories\Content
 */
class Content extends Model implements ContentStructure
{
    use SoftDeletes, ContentMetaSetterAndGetterTrait;

    protected $dates = [
        'published_at',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function entity()
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function node()
    {
        return $this->morphOne(TreeNode::class, 'content', 'content_type', 'content_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id', 'id');
    }

    /**
     * 创建内容
     *
     * @param ContentStructure|Model $entity
     * @param string|array|User      $author
     *
     * @return bool
     */
    public function make(ContentStructure $entity, $author = null)
    {
        if (!$entity instanceof Model) {
            throw new InvalidArgumentException();
        }

        $this->title = $entity->getTitle();
        $this->keywords = $entity->getKeywords();
        $this->description = $entity->getDescription();

        if (!is_null($author)) {
            $this->setAuthor($author);
        }

        if (!$entity->exists) {
            $entity->saveOrFail();
        }

        $this->entity()->associate($entity);

        return $this->save();
    }

    /**
     * 设置内容作者
     *
     * @param User|string|array $author
     *
     * @return $this
     */
    public function setAuthor($author)
    {
        if (is_string($author)) {
            $this->author_name = $author;
        } elseif ($author instanceof User) {
            $this->author()->associate($author);
            $this->author_name = $author->getNickname();
        } elseif (is_array($author)) {
            list($author, $authorName) = $author;
            $this->author()->associate($author);
            $this->author_name = $authorName;
        }

        return $this;
    }

    /**
     * 发布内容
     *
     * @param bool $republish 是否重新发布
     *
     * @return bool
     */
    public function publish($republish = false)
    {
        if (!$this->exists) {
            throw new OperationRejectedException();
        }

        if ($this->published_at && !$republish) {
            return false;
        }

        $this->published_at = Carbon::now();
        $this->save();

        if (isset(static::$dispatcher)) {
            static::$dispatcher->dispatch(new ContentPublished($this, $republish));
        }

        return true;
    }
}
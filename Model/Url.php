<?php
namespace Mirasvit\Blog\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface as MagentoUrlInterface;
use Magento\Framework\DataObject;

class Url
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var PostFactory
     */
    protected $postFactory;

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var TagFactory
     */
    protected $tagFactory;

    /**
     * @var AuthorFactory
     */
    protected $authorFactory;

    /**
     * @var MagentoUrlInterface
     */
    protected $urlManager;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Config               $config
     * @param ScopeConfigInterface $scopeConfig
     * @param PostFactory          $postFactory
     * @param CategoryFactory      $categoryFactory
     * @param TagFactory           $tagFactory
     * @param AuthorFactory        $authorFactory
     * @param MagentoUrlInterface  $urlManager
     */
    public function __construct(
        Config $config,
        ScopeConfigInterface $scopeConfig,
        PostFactory $postFactory,
        CategoryFactory $categoryFactory,
        TagFactory $tagFactory,
        AuthorFactory $authorFactory,
        MagentoUrlInterface $urlManager
    ) {
        $this->config = $config;
        $this->scopeConfig = $scopeConfig;
        $this->postFactory = $postFactory;
        $this->categoryFactory = $categoryFactory;
        $this->tagFactory = $tagFactory;
        $this->authorFactory = $authorFactory;
        $this->urlManager = $urlManager;
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->urlManager->getUrl($this->config->getBaseRoute());
    }

    /**
     * @param Post $post
     * @return string
     */
    public function getPostUrl($post)
    {
        return $this->getUrl('/' . $post->getUrlKey(), 'post');
    }

    /**
     * @param Category $category
     * @param array    $urlParams
     * @return string
     */
    public function getCategoryUrl($category, $urlParams = [])
    {
        return $this->getUrl('/' . $category->getUrlKey(), 'category', $urlParams);
    }

    /**
     * @param Category $category
     * @return string
     */
    public function getRssUrl($category = null)
    {
        if ($category) {
            return $this->getUrl('/rss/' . $category->getUrlKey(), 'rss');
        }

        return $this->getUrl('/rss', 'rss');
    }

    /**
     * @param Tag   $tag
     * @param array $urlParams
     * @return string
     */
    public function getTagUrl($tag, $urlParams = [])
    {
        return $this->getUrl('/tag/' . strtolower($tag->getUrlKey()), 'tag', $urlParams);
    }

    /**
     * @param Author $author
     * @param array  $urlParams
     * @return string
     */
    public function getAuthorUrl($author, $urlParams = [])
    {
        return $this->getUrl('/author/' . strtolower($author->getId()), 'author', $urlParams);
    }

    /**
     * @param array $urlParams
     * @return string
     */
    public function getSearchUrl($urlParams = [])
    {
        return $this->getUrl('/search/', 'search', $urlParams);
    }

    /**
     * @param string $route
     * @param string $type
     * @param array  $urlParams
     * @return string
     */
    protected function getUrl($route, $type, $urlParams = [])
    {
        $url = $this->urlManager->getUrl($this->config->getBaseRoute() . $route, $urlParams);

        if ($type == 'post' && $this->config->getPostUrlSuffix()) {
            $url = $this->addSuffix($url, $this->config->getPostUrlSuffix());
        }

        if ($type == 'category' && $this->config->getCategoryUrlSuffix()) {
            $url = $this->addSuffix($url, $this->config->getCategoryUrlSuffix());
        }

        return $url;
    }

    private function addSuffix($url, $suffix)
    {
        $parts = explode('?', $url, 2);
        $parts[0] = rtrim($parts[0], '/') . $suffix;

        return implode('?', $parts);
    }

    /**
     * @param string $pathInfo
     * @return bool|DataObject
     */
    public function match($pathInfo)
    {
        $identifier = trim($pathInfo, '/');
        $parts = explode('/', $identifier);

        if (count($parts) >= 1) {
            $parts[count($parts) - 1] = $this->trimSuffix($parts[count($parts) - 1]);
        }

        if ($parts[0] != $this->config->getBaseRoute()) {
            return false;
        }

        if (count($parts) > 1) {
            unset($parts[0]);
            $parts = array_values($parts);
            $urlKey = implode('/', $parts);
            $urlKey = urldecode($urlKey);
            $urlKey = $this->trimSuffix($urlKey);
        } else {
            $urlKey = '';
        }

        if ($urlKey == '') {
            return new DataObject([
                'module_name'     => 'blog',
                'controller_name' => 'category',
                'action_name'     => 'index',
                'params'          => [],
            ]);
        }

        if ($parts[0] == 'search') {
            return new DataObject([
                'module_name'     => 'blog',
                'controller_name' => 'search',
                'action_name'     => 'result',
                'params'          => [],
            ]);
        }

        if ($parts[0] == 'tag' && isset($parts[1])) {
            $tag = $this->tagFactory->create()->getCollection()
                ->addFieldToFilter('url_key', $parts[1])
                ->getFirstItem();

            if ($tag->getId()) {
                return new DataObject([
                    'module_name'     => 'blog',
                    'controller_name' => 'tag',
                    'action_name'     => 'view',
                    'params'          => ['id' => $tag->getId()],
                ]);
            } else {
                return false;
            }
        }

        if ($parts[0] == 'author' && isset($parts[1])) {
            $author = $this->authorFactory->create()->getCollection()
                ->addFieldToFilter('user_id', $parts[1])
                ->getFirstItem();

            if ($author->getId()) {
                return new DataObject([
                    'module_name'     => 'blog',
                    'controller_name' => 'author',
                    'action_name'     => 'view',
                    'params'          => ['id' => $author->getId()],
                ]);
            } else {
                return false;
            }
        }

        if ($parts[0] == 'rss' && isset($parts[1])) {
            $category = $this->categoryFactory->create()->getCollection()
                ->addFieldToFilter('url_key', $parts[1])
                ->getFirstItem();

            if ($category->getId()) {
                return new DataObject([
                    'module_name'     => 'blog',
                    'controller_name' => 'category',
                    'action_name'     => 'rss',
                    'params'          => ['id' => $category->getId()],
                ]);
            } else {
                return false;
            }
        } elseif ($parts[0] == 'rss') {
            return new DataObject([
                'module_name'     => 'blog',
                'controller_name' => 'category',
                'action_name'     => 'rss',
                'params'          => [],
            ]);
        }

        $post = $this->postFactory->create()->getCollection()
            ->addAttributeToFilter('url_key', $urlKey)
            ->getFirstItem();

        if ($post->getId()) {
            return new DataObject([
                'module_name'     => 'blog',
                'controller_name' => 'post',
                'action_name'     => 'view',
                'params'          => ['id' => $post->getId()],
            ]);
        }

        $category = $this->categoryFactory->create()->getCollection()
            ->addAttributeToFilter('url_key', $urlKey)
            ->getFirstItem();

        if ($category->getId()) {
            return new DataObject([
                'module_name'     => 'blog',
                'controller_name' => 'category',
                'action_name'     => 'view',
                'params'          => ['id' => $category->getId()],
            ]);
        }

        return false;
    }

    /**
     * Return url without suffix
     *
     * @param string $key
     * @return string
     */
    protected function trimSuffix($key)
    {
        $suffix = $this->config->getCategoryUrlSuffix();
        //user can enter .html or html suffix
        if ($suffix != '' && $suffix[0] != '.') {
            $suffix = '.' . $suffix;
        }

        $key = str_replace($suffix, '', $key);

        $suffix = $this->config->getPostUrlSuffix();
        //user can enter .html or html suffix
        if ($suffix != '' && $suffix[0] != '.') {
            $suffix = '.' . $suffix;
        }

        $key = str_replace($suffix, '', $key);

        return $key;
    }
}
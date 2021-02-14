<?php

namespace Apie\PaginationPlugin;

use Apie\Core\Events\DecodeEvent;
use Apie\Core\Events\DeleteResourceEvent;
use Apie\Core\Events\ModifySingleResourceEvent;
use Apie\Core\Events\NormalizeEvent;
use Apie\Core\Events\ResponseAllEvent;
use Apie\Core\Events\ResponseEvent;
use Apie\Core\Events\RetrievePaginatedResourcesEvent;
use Apie\Core\Events\RetrieveSingleResourceEvent;
use Apie\Core\Events\StoreExistingResourceEvent;
use Apie\Core\Events\StoreNewResourceEvent;
use Apie\Core\PluginInterfaces\ApieAwareInterface;
use Apie\Core\PluginInterfaces\ApieAwareTrait;
use Apie\Core\PluginInterfaces\NormalizerProviderInterface;
use Apie\Core\PluginInterfaces\OpenApiEventProviderInterface;
use Apie\Core\PluginInterfaces\ResourceLifeCycleInterface;
use Apie\Core\SearchFilters\SearchFilterRequest;
use Apie\OpenapiSchema\Map\HeaderMap;
use Apie\OpenapiSchema\Spec\Components;
use Apie\OpenapiSchema\Spec\Document;
use Apie\OpenapiSchema\Spec\Operation;
use Apie\OpenapiSchema\Spec\PathItem;
use Apie\OpenapiSchema\Spec\Reference;
use Apie\PaginationPlugin\Normalizers\PaginatorNormalizer;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;

class PaginationPlugin implements ResourceLifeCycleInterface, NormalizerProviderInterface, OpenApiEventProviderInterface, ApieAwareInterface
{
    use ApieAwareTrait;

    const PREV_HEADER = 'x-pagination-previous';

    const NEXT_HEADER = 'x-pagination-next';

    const FIRST_HEADER = 'x-pagination-first';

    const LAST_HEADER = 'x-pagination-last';

    const COUNT_HEADER = 'x-pagination-count';

    public function getNormalizers(): array
    {
        return [new PaginatorNormalizer()];
    }

    public function onOpenApiDocGenerated(Document $document): Document
    {
        $paths = $document->getPaths();
        $added = false;
        foreach ($paths as $url => $path) {
            if (strpos($url, '{id}', 0) === false && $path instanceof PathItem && $path->getGet() && $this->patch($path->getGet())) {
                $added = true;
            }
        }
        if ($added) {
            $components = $document->getComponents();
            if (!$components) {
                $components = Components::fromNative([]);
            }
            $headers = $components->getHeaders();
            $headers = $headers->with('Count', ['description' => 'Number of results', 'schema' => ['type' => 'number', 'format' => 'int']])
                ->with('Url', ['description' => 'pagination url', 'schema' => ['type' => 'string', 'format' => 'url']]);
            $components = $components->with('headers', $headers);
            return $document->with('components', $components);
        }
        return $document;
    }

    private function patch(Operation $operation): bool
    {
        $added = false;
        foreach ($operation->getResponses() as $key => $response) {
            if ($response instanceof Reference) {
                continue;
            }
            $added = true;
            $countSchema = new Reference('#/components/headers/Count');
            $urlSchema = new Reference('#/components/headers/Url');
            $headers = ($response->getHeaders() ?? new HeaderMap())->toNative();
            $headers[self::COUNT_HEADER] = $countSchema;
            $headers[self::PREV_HEADER] = $urlSchema;
            $headers[self::NEXT_HEADER] = $urlSchema;
            $headers[self::FIRST_HEADER] = $urlSchema;
            $headers[self::LAST_HEADER] = $urlSchema;
            $response = $response->with('headers', $headers);
            $operation = $operation->with($key, $response);
        }
        return $added;
    }

    public function onPreDeleteResource(DeleteResourceEvent $event)
    {
    }

    public function onPostDeleteResource(DeleteResourceEvent $event)
    {
    }

    public function onPreRetrieveResource(RetrieveSingleResourceEvent $event)
    {
    }

    public function onPostRetrieveResource(RetrieveSingleResourceEvent $event)
    {
    }

    public function onPreRetrieveAllResources(RetrievePaginatedResourcesEvent $event)
    {
    }

    public function onPostRetrieveAllResources(RetrievePaginatedResourcesEvent $event)
    {
    }

    public function onPrePersistExistingResource(StoreExistingResourceEvent $event)
    {
    }

    public function onPostPersistExistingResource(StoreExistingResourceEvent $event)
    {
    }

    public function onPreDecodeRequestBody(DecodeEvent $event)
    {
    }

    public function onPostDecodeRequestBody(DecodeEvent $event)
    {
    }

    public function onPreModifyResource(ModifySingleResourceEvent $event)
    {
    }

    public function onPostModifyResource(ModifySingleResourceEvent $event)
    {
    }

    public function onPreCreateResource(StoreNewResourceEvent $event)
    {
    }

    public function onPostCreateResource(StoreNewResourceEvent $event)
    {
    }

    public function onPrePersistNewResource(StoreExistingResourceEvent $event)
    {
    }

    public function onPostPersistNewResource(StoreExistingResourceEvent $event)
    {
    }

    public function onPreCreateResponse(ResponseEvent $event)
    {
    }

    public function onPostCreateResponse(ResponseEvent $event)
    {
        if (!($event instanceof ResponseAllEvent)) {
            return;
        }
        $resource = $event->getResource();
        if (!($resource instanceof Pagerfanta)) {
            if (is_array($resource)) {
                $resource = new Pagerfanta(new ArrayAdapter($resource));
            } else if (is_iterable($resource)) {
                $resource = new Pagerfanta(new ArrayAdapter(iterator_to_array($resource)));
            } else {
                return;
            }
            $event->getSearchFilterRequest()->updatePaginator($resource);
        }
        $response = $event->getResponse()
            ->withHeader(self::FIRST_HEADER, $this->generateUrl($event, 0))
            ->withHeader(self::LAST_HEADER, $this->generateUrl($event, $resource->getNbPages() - 1))
            ->withHeader(self::COUNT_HEADER, $resource->getNbResults());
        if ($resource->hasPreviousPage()) {
            $response = $response->withHeader(self::PREV_HEADER, $this->generateUrl($event, $resource->getPreviousPage() - 1));
        }
        if ($resource->hasNextPage()) {
            $response = $response->withHeader(self::NEXT_HEADER, $this->generateUrl($event, $resource->getNextPage() - 1));
        }
        $event->setResponse($response);
    }

    private function generateUrl(ResponseAllEvent  $event, int $page)
    {
        $filterRequest = new SearchFilterRequest($page, $event->getSearchFilterRequest()->getNumberOfItems());
        return $this->getApie()->getOverviewUrlForResourceClass($event->getResourceClass(), $filterRequest);
    }

    public function onPreCreateNormalizedData(NormalizeEvent $event)
    {
    }

    public function onPostCreateNormalizedData(NormalizeEvent $event)
    {
    }
}

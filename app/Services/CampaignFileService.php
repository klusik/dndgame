<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CampaignRepository;
use App\Repositories\EntityRepository;
use App\Repositories\FactRepository;
use App\Repositories\MysteryRepository;

/**
 * Maintains file mirrors for campaign data stored in the database.
 */
final class CampaignFileService
{
    /**
     * Store dependencies used to create and refresh campaign files.
     */
    public function __construct(
        private FileStorage $storage,
        private CampaignRepository $campaigns,
        private EntityRepository $entities,
        private FactRepository $facts,
        private MysteryRepository $mysteries
    ) {
    }

    /**
     * Create initial folders and base files for a new campaign.
     *
     * @param array<string, mixed> $campaign
     */
    public function initialize(array $campaign, string $description): void
    {
        $base = (string) $campaign['storage_path'];
        foreach (['sessions', 'prompts', 'responses', 'deltas', 'exports', 'mirrors', 'events', 'notes'] as $dir) {
            $this->storage->ensureDirectory($base . '/' . $dir);
        }

        $this->storage->write($base . '/README.md', '# ' . $campaign['title'] . "\n\n" . trim($description) . "\n");
        $this->storage->write($base . '/notes/campaign_notes.md', "# Campaign notes\n\n");
        $this->refreshMirrors((int) $campaign['id']);
    }

    /**
     * Refresh JSON mirrors from database indexes.
     */
    public function refreshMirrors(int $campaignId): void
    {
        $campaign = $this->campaigns->find($campaignId);
        if (!$campaign) {
            return;
        }

        $base = (string) $campaign['storage_path'];
        $this->storage->writeJson($base . '/mirrors/campaign.json', $campaign);
        $this->storage->writeJson($base . '/mirrors/entities.json', $this->entities->listByCampaign($campaignId));
        $this->storage->writeJson($base . '/mirrors/facts.json', $this->facts->listCurrentByCampaign($campaignId, true));
        $this->storage->writeJson($base . '/mirrors/mysteries.json', $this->mysteries->listByCampaign($campaignId));
    }
}

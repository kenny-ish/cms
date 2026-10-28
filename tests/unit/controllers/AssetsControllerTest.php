<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace crafttests\unit\controllers;

use Craft;
use craft\controllers\AssetsController;
use craft\elements\Asset;
use craft\elements\User;
use craft\test\TestCase;
use crafttests\fixtures\AssetFixture;
use UnitTester;

/**
 * Unit tests for AssetsController
 */
class AssetsControllerTest extends TestCase
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @see https://github.com/craftcms/cms/issues/20128
     */
    public function testMoveAssetReplacingConflictingAsset(): void
    {
        $this->tester->haveFixtures([
            'assets' => AssetFixture::class,
        ]);

        /** @var Asset $conflictingAsset */
        $conflictingAsset = Asset::find()->filename('shinybrad.png')->one();

        // An asset with the same filename in another volume
        /** @var Asset $asset */
        $asset = Asset::find()->filename('shinybrad2.png')->one();
        $asset->setFilename('shinybrad.png');
        self::assertNotSame($conflictingAsset->getVolumeId(), $asset->getVolumeId());

        $mergedIds = null;
        $this->tester->mockCraftMethods('assets', [
            'getAssetById' => $asset,
            'moveAsset' => true,
        ]);
        $this->tester->mockCraftMethods('elements', [
            'mergeElementsByIds' => function(int $mergedElementId, int $prevailingElementId) use (&$mergedIds): bool {
                $mergedIds = [$mergedElementId, $prevailingElementId];
                return true;
            },
        ]);

        Craft::$app->getUser()->setIdentity(new User([
            'id' => 1,
            'admin' => true,
        ]));

        $request = Craft::$app->getRequest();
        $request->setBodyParams([
            'assetId' => $asset->id,
            'folderId' => $conflictingAsset->folderId,
            'force' => true,
        ]);
        $request->getHeaders()->set('Accept', 'application/json');

        $controller = new AssetsController('assets', Craft::$app);
        $response = $controller->actionMoveAsset();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([$conflictingAsset->id, $asset->id], $mergedIds);
    }

    protected function _after(): void
    {
        Craft::$app->getUser()->setIdentity(null);

        parent::_after();
    }
}

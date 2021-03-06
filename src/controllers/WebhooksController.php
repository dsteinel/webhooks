<?php

namespace craft\webhooks\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller as BaseController;
use craft\webhooks\assets\edit\EditAsset;
use craft\webhooks\Plugin;
use craft\webhooks\Webhook;
use yii\base\InvalidArgumentException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Webhooks Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class WebhooksController extends BaseController
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('accessPlugin-webhooks');

        return true;
    }

    /**
     * Shows the edit page for a webhook.
     *
     * @param int|null $id
     * @param int|null $groupId
     * @param Webhook|null $webhook
     * @return Response
     * @throws NotFoundHttpException if $id is invalid
     */
    public function actionEdit(int $id = null, int $groupId = null, Webhook $webhook = null): Response
    {
        $manager = Plugin::getInstance()->getWebhookManager();

        if ($webhook === null) {
            if ($id !== null) {
                try {
                    $webhook = $manager->getWebhookById($id);
                } catch (InvalidArgumentException $e) {
                    throw new NotFoundHttpException($e->getMessage(), 0, $e);
                }
            } else {
                $webhook = new Webhook();
                if ($groupId !== null) {
                    $webhook->groupId = $groupId;
                }
            }
        }

        if ($webhook->id) {
            $title = trim($webhook->name) ?: Craft::t('webhooks', 'Edit Webhook');
        } else {
            $title = Craft::t('webhooks', 'Create a new webhook');
        }

        $crumbs = [
            [
                'label' => Craft::t('webhooks', 'Webhooks'),
                'url' => UrlHelper::url('webhooks')
            ]
        ];

        // Groups
        $groupOptions = [
            ['value' => null, 'label' => Craft::t('webhooks', '(Ungrouped)')]
        ];

        foreach ($manager->getAllGroups() as $group) {
            $groupOptions[] = ['value' => $group->id, 'label' => $group->name];

            if ($webhook->groupId && $webhook->groupId == $group->id) {
                $crumbs[] = [
                    'label' => $group->name,
                    'url' => UrlHelper::url("webhooks/group/{$group->id}")
                ];
            }
        }

        Craft::$app->getView()->registerAssetBundle(EditAsset::class);

        return $this->renderTemplate('webhooks/_edit', compact(
            'groupOptions',
            'webhook',
            'title',
            'crumbs'
        ));
    }

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $manager = Plugin::getInstance()->getWebhookManager();

        $id = $request->getBodyParam('id');

        if ($id) {
            try {
                $webhook = $manager->getWebhookById($id);
            } catch (InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage(), 0, $e);
            }
        } else {
            $webhook = new Webhook();
        }

        $webhook->setAttributes($request->getBodyParams());

        if (!Plugin::getInstance()->getWebhookManager()->saveWebhook($webhook)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $webhook->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('webhooks', 'Couldn’t save webhook.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'webhook' => $webhook,
            ]);
            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'webhook' => $webhook->toArray(),
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('webhooks', 'Webhook saved.'));
        return $this->redirectToPostedUrl($webhook);
    }

    /**
     * Deletes a webhook.
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        Plugin::getInstance()->getWebhookManager()->deleteWebhookById($id);
        return $this->redirectToPostedUrl();
    }
}

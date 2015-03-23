<?php
namespace Craft;

class CommentsController extends BaseController
{
    protected $allowAnonymous = array('actionAdd');

    public function actionEdit(array $variables = array())
    {
        $commentId = $variables['commentId'];
        $comment = craft()->comments->getCommentById($commentId);

        $variables['comment'] = $comment;

        $this->renderTemplate('comments/edit', $variables);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $commentId = craft()->request->getRequiredPost('commentId');
        $comment = craft()->request->getRequiredPost('comment');
        $status = craft()->request->getRequiredPost('status');

        $commentModel = craft()->comments->getCommentById($commentId);
        $commentModel->status = $status;
        $commentModel->comment = $comment;

        if ($result = craft()->comments->saveComment($commentModel)) {
            craft()->userSession->setNotice(Craft::t('Comment saved successfully.'));
        } else {
            craft()->userSession->setError($result);
        }
    }

    public function actionAdd()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();
        $plugin = craft()->plugins->getPlugin('comments');

        $commentModel = new Comments_CommentModel();

        $commentModel->entryId = craft()->request->getPost('entryId');
        $commentModel->userId = craft()->request->getPost('userId');
        $commentModel->parentId = craft()->request->getPost('parentId');
        $commentModel->structureId = craft()->comments->getStructureId();
        
        // Other handy stuff
        $commentModel->url = craft()->request->urlReferrer;
        $commentModel->ipAddress = craft()->request->getUserHostAddress();
        $commentModel->userAgent = craft()->request->getUserAgent();
            
        // Handle the fields
        $fields = craft()->request->getPost('fields');
        $commentModel->name = array_key_exists('name', $fields) ? $fields['name'] : null;
        $commentModel->email = array_key_exists('email', $fields) ? $fields['email'] : null;
        $commentModel->comment = array_key_exists('comment', $fields) ? $fields['comment'] : null;

        // Set any new comment to be pending
        if ($plugin->getSettings()->requireModeration) {
            $commentModel->status = Comments_CommentModel::PENDING;
        }


        // Protect against Anonymous submissions, if turned off
        if (!$plugin->getSettings()->allowAnonymous && !$commentModel->userId) {
            $this->returnJson(array('error' => 'Must be logged in to comment'));
        }

        // Must have an actual comment
        if (!$commentModel->comment) {
            $this->returnJson(array('error' => 'Comment must not be blank'));
        }

        // Is this user logged in? Or they've provided user/email?
        if ($commentModel->userId || ($commentModel->name && $commentModel->email)) {
            $result = craft()->comments->saveComment($commentModel);

            if (!array_key_exists('error', $result)) {
                $this->returnJson(array('success' => true));
            } else {
                $this->returnJson($result);
            }
        } else {
            $this->returnJson(array('error' => 'Must be logged in, or supply Name/Email to comment'));
        }
    }

}
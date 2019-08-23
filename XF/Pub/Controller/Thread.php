<?php
/**
 * Question Threads xF2 addon by CMTV
 * Enjoy!
 */

namespace CMTV\QuestionThreads\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

use CMTV\QuestionThreads\Constants as C;

class Thread extends XFCP_Thread
{
    public function actionMarkSolved(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);

        $options = \XF::options();

        if ($options->CMTV_QT_closeThread) {
          $editor = $this->getEditorService($thread);
          $editor->setDiscussionOpen(false);
          $editor->save();
        }
        return $this->markGeneric($params->thread_id, true);
    }

    public function actionMarkUnsolved(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);

        $options = \XF::options();

        if ($options->CMTV_QT_closeThread) {
          $editor = $this->getEditorService($thread);
          $editor->setDiscussionOpen(true);
          $editor->save();
        }
        return $this->markGeneric($params->thread_id, false);
    }

    protected function markGeneric(int $threadId, bool $solved)
    {
        /** @var \CMTV\QuestionThreads\XF\Entity\Thread $thread */
        $thread = $this->assertViewableThread($threadId);

        if (!$thread->CMTV_QT_is_question)
        {
            return $this->error(\XF::phrase(C::_('only_questions_can_be_marked_as_solved_unsolved')));
        }

        if (!$thread->{'canMark' . ($solved ? 'Solved' : 'Unsolved')}())
        {
            return $this->noPermission();
        }

        $thread->CMTV_QT_is_solved = $solved;

        if (!$thread->preSave())
        {
            return $this->error($thread->getErrors());
        }

        $thread->save();

        // Creating news feed entry

        /** @var \CMTV\QuestionThreads\Repository\Thread $threadRepo */
        $threadRepo = $this->repository(C::__('Thread'));

        if ($solved)
        {
            $threadRepo->publishMarkedSolved(\XF::visitor(), $thread);
            $threadRepo->alertWatchers(\XF::visitor(), $thread);
        }
        else
        {
            $threadRepo->unpublishMarkedSolved($thread);
        }

        return $this->redirect($this->buildLink('threads', $thread));
    }

    protected function setupThreadEdit(\XF\Entity\Thread $thread)
    {
        $visitor = \XF::visitor();

        $editor = parent::setupThreadEdit($thread);

        if ($visitor->hasPermission(C::_(), 'editAnyThreadType'))
        {
            /** @var \CMTV\QuestionThreads\XF\Entity\Thread $thread */
            $thread = $editor->getThread();
            $thread->CMTV_QT_is_question = $this->filter(C::_('is_question'), 'bool');
        }

        return $editor;
    }
}

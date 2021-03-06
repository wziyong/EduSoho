<?php
namespace Topxia\Service\Testpaper\Event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Topxia\Common\StringToolkit;
use Topxia\WebBundle\Util\TargetHelper;

use Topxia\Service\Common\ServiceEvent;
use Topxia\Service\Common\ServiceKernel;
use Topxia\Common\ArrayToolkit;

class TestpaperEventSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return array(
            'testpaper.finish' => 'onTestpaperFinish',
            'testpaper.create' => 'onTestpaperCreate',
            'testpaper.update' => 'onTestpaperUpdate',
            'testpaper.delete' => 'onTestpaperDelete',
            'testpaper.items.create' => 'onTestpaperItemCreate',
            'testpaper.items.update' => 'onTestpaperItemUpdate',
            'testpaper.items.delete' => 'onTestpaperItemDelete'
        );
    }

    public function onTestpaperFinish(ServiceEvent $event)
    {
        $testpaper = $event->getSubject();
        $testpaperResult = $event->getArgument('testpaperResult');
        //TODO need to use targetHelper class for consistency
        $target = explode('-', $testpaper['target']);
        $course = $this->getCourseService()->getCourse($target[1]);
        $this->getStatusService()->publishStatus(array(
            'type' => 'finished_testpaper',
            'objectType' => 'testpaper',
            'objectId' => $testpaper['id'],
            'private' => $course['status'] == 'published' ? 0 : 1,
            'properties' => array(
                'testpaper' => $this->simplifyTestpaper($testpaper),
                'result' => $this->simplifyTestpaperResult($testpaperResult),
            )
        ));
    }

    public function onTestpaperCreate(ServiceEvent $event)
    {
        $testpaper = $event->getSubject();
        $items = $event->getArgument('items');
        $id = $testpaper['id'];
        $testpaperTarget = explode('-',$testpaper['target']);
        $courseId = $testpaperTarget[1];
        $courseIds = ArrayToolkit::column($this->getCourseService()->findCoursesByParentIdAndLocked($courseId,1),'id');

        if($courseIds){
            $testpaper['pId'] = $testpaper['id'];
            unset($testpaper['id']);        
            foreach ($courseIds as  $courseId) {
                $testpaper['target'] = "course-".$courseId;
                $addtestpaper = $this->getTestpaperService()->addTestpaper($testpaper);
                foreach($items as $item) {
                    $item['pId'] = $item['id'];
                    unset($item['id']);
                    $item['testId'] = $addtestpaper['id'];
                    $this->getTestpaperService()->createTestpaperItem($item);
                }
            }


            $lockedTarget = '';
            foreach ($courseIds as $courseId) {
                    $lockedTarget .= "'course-".$courseId."',";
            }
            $lockedTarget = "(".trim($lockedTarget,',').")";
            $testpaperIds = ArrayToolkit::column($this->getTestpaperService()->findTestpapersByPIdAndLockedTarget($id,$lockedTarget),'id');

            $testpaper = $this->getTestpaperService()->getTestpaper($id);
            $fields = array("score"=>$testpaper['score'],"itemCount"=>$testpaper['itemCount'],"metas"=>$testpaper['metas']);
            foreach ($testpaperIds as $testpaperId) {
                $this->getTestpaperService()->editTestpaper($testpaperId, $fields);
            }
        }
    }


    public function onTestpaperUpdate(ServiceEvent $event)
    {
        $testpaper = $event->getSubject();
        $testpaperTarget = explode('-',$testpaper['target']);
        $courseId = $testpaperTarget[1];
        $courseIds = ArrayToolkit::column($this->getCourseService()->findCoursesByParentIdAndLocked($courseId,1),'id');
        
        if($courseIds){
            $lockedTarget = '';
            foreach ($courseIds as $courseId) {
                    $lockedTarget .= "'course-".$courseId."',";
            }
            $lockedTarget = "(".trim($lockedTarget,',').")";
            $testpaperIds = ArrayToolkit::column($this->getTestpaperService()->findTestpapersByPIdAndLockedTarget($testpaper['id'],$lockedTarget),'id');
            unset($testpaper['id'],$testpaper['target'],$testpaper['createdTime'],$testpaper['updatedTime'],$testpaper['pId']);
            foreach ($testpaperIds as $testpaperId) {
               $this->getTestpaperService()->editTestpaper($testpaperId,$testpaper);
            }  
        }
    }

    public function onTestpaperDelete(ServiceEvent $event)
    {
       $testpaper = $event->getSubject();
       $testpaperId = $testpaper['id'];
       $testpaperTarget = explode('-',$testpaper['target']);
       $courseId = $testpaperTarget[1];
       $courseIds = ArrayToolkit::column($this->getCourseService()->findCoursesByParentIdAndLocked($courseId,1),'id');
       if($courseIds){
            $lockedTarget = '';
            foreach ($courseIds as $courseId) {
                    $lockedTarget .= "'course-".$courseId."',";
            }
            $lockedTarget = "(".trim($lockedTarget,',').")";
            $testpaperIds = ArrayToolkit::column($this->getTestpaperService()->findTestpapersByPIdAndLockedTarget($testpaperId,$lockedTarget),'id');
            foreach ($testpaperIds as $testpaperId) {
              $this->getTestpaperService()->deleteTestpaper($testpaperId);
            }
       }
    }

    public function onTestpaperItemCreate(ServiceEvent $event)
    {
        $context = $event->getSubject();
        $item = $context['item'];
        $testpaper = $context['testpaper'];
        $testpaperTarget = explode('-',$testpaper['target']);
        $courseId = $testpaperTarget[1];
        $courseIds = ArrayToolkit::column($this->getCourseService()->findCoursesByParentIdAndLocked($courseId,1),'id');
        if($courseIds){
            $lockedTarget = '';
            foreach ($courseIds as $courseId) {
                $lockedTarget .= "'course-".$courseId."',";
            }
            $lockedTarget = "(".trim($lockedTarget,',').")";
            $testpaperIds = ArrayToolkit::column($this->getTestpaperService()->findTestpapersByPIdAndLockedTarget($testpaper['id'],$lockedTarget),'id');
            $item['pId'] = $item['id'];
            unset($item['id']);
            foreach ($testpaperIds as $testpaperId) {
                $item['testId'] = $testpaperId;
                $this->getTestpaperService()->createTestpaperItem($item);
            }
        }
    }

    public function onTestpaperItemUpdate(ServiceEvent $event)
    {
        $context = $event->getSubject();
        $items = $context['items'];
        $testpaper = $context['testpaper'];

        $testpaperTarget = explode('-',$testpaper['target']);
        $courseId = $testpaperTarget[1];
        $courseIds = ArrayToolkit::column($this->getCourseService()->findCoursesByParentIdAndLocked($courseId,1),'id');
        //判断是否是一维数组
        if(array_key_exists('id', $items)){

            if($courseIds){
                $lockedTarget = ''; 
                foreach ($courseIds as $courseId) {
                    $lockedTarget .= "'course-".$courseId."',";
                }       
                $lockedTarget = "(".trim($lockedTarget,',').")";

                $testpaperIds = ArrayToolkit::column($this->getTestpaperService()->findTestpapersByPIdAndLockedTarget($testpaper['id'],$lockedTarget),'id');
                $testpaperItemIds = ArrayToolkit::column($this->getTestpaperService()->findTestpaperItemsByPIdAndLockedTestIds($items['id'],$testpaperIds),'id');
                foreach ($testpaperItemIds as $testpaperItemId) {
                     $this->getTestpaperService()->editTestpaperItem($testpaperItemId,array('seq'=>$items['seq'],'score'=>$items['score']));
                }
            }
            return; 
        }

        
        if($courseIds){
            $lockedTarget = '';
            foreach ($courseIds as $courseId) {
                $lockedTarget .= "'course-".$courseId."',";
            }
            $lockedTarget = "(".trim($lockedTarget,',').")";
            
            //重新生成题目
            $testpaperIds = ArrayToolkit::column($this->getTestpaperService()->findTestpapersByPIdAndLockedTarget($testpaper['id'],$lockedTarget),'id');
            foreach ($testpaperIds as $testpaperId) {
                $this->getTestpaperService()->deleteTestpaperItemByTestId($testpaperId);
                foreach ($items as $item) {
                    $item['pId'] = $item['id'];
                    unset($item['id']);
                    $item['testId'] = $testpaperId;
                    $this->getTestpaperService()->createTestpaperItem($item);
                }
            }

            $testpaper = $this->getTestpaperService()->getTestpaper($testpaper['id']);
            $fields = array("score"=>$testpaper['score'],"itemCount"=>$testpaper['itemCount'],"metas"=>$testpaper['metas']);
            foreach ($testpaperIds as $testpaperId) {
                $this->getTestpaperService()->editTestpaper($testpaperId, $fields);
            }
        }
    }

    public function onTestpaperItemDelete(ServiceEvent $event)
    {
        $context = $event->getSubject();
        $item = $context['existItem'];
        $testpaper = $context['testpaper'];

        $testpaperTarget = explode('-',$testpaper['target']);
        $courseId = $testpaperTarget[1];
        $courseIds = ArrayToolkit::column($this->getCourseService()->findCoursesByParentIdAndLocked($courseId,1),'id');        
    
        if($courseIds){
            $lockedTarget = ''; 
            foreach ($courseIds as $courseId) {
                $lockedTarget .= "'course-".$courseId."',";
            }       
            $lockedTarget = "(".trim($lockedTarget,',').")";

            $testpaperIds = ArrayToolkit::column($this->getTestpaperService()->findTestpapersByPIdAndLockedTarget($testpaper['id'],$lockedTarget),'id');  
            $testpaperItemIds = ArrayToolkit::column($this->getTestpaperService()->findTestpaperItemsByPIdAndLockedTestIds($item['id'],$testpaperIds),'id');
            foreach ($testpaperItemIds as $testpaperItemId) {
                $this->getTestpaperService()->deleteTestpaperItem($testpaperItemId);
            }
        }  
    }

    protected function simplifyTestpaper($testpaper)
    {
        return array(
            'id' => $testpaper['id'],
            'name' => $testpaper['name'],
            'description' => StringToolkit::plain($testpaper['description'], 100),
            'score' => $testpaper['score'],
            'passedScore' => $testpaper['passedScore'],
            'itemCount' => $testpaper['itemCount'],
        );
    }

    protected function simplifyTestpaperResult($testpaperResult)
    {
        return array(
            'id' => $testpaperResult['id'],
            'score' => $testpaperResult['score'],
            'objectiveScore' => $testpaperResult['objectiveScore'],
            'subjectiveScore' => $testpaperResult['subjectiveScore'],
            'teacherSay' => StringToolkit::plain($testpaperResult['teacherSay'], 100),
            'passedStatus' => $testpaperResult['passedStatus'],
        );
    }

    protected function getCourseService()
    {
        return ServiceKernel::instance()->createService('Course.CourseService');
    }

    protected function getStatusService()
    {
        return ServiceKernel::instance()->createService('User.StatusService');
    }

    protected function getTestpaperService()
    {
        return ServiceKernel::instance()->createService('Testpaper.TestpaperService');
    }
}

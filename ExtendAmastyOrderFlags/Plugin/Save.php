<?php

namespace DevAll\ExtendAmastyOrderFlags\Plugin;

use Amasty\Flags\Api\ColumnRepositoryInterface;
use Amasty\Flags\Api\FlagRepositoryInterface;
use Amasty\Flags\Model\Column;
use Amasty\Flags\Model\Flag;
use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\File\Uploader;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;

class Save extends \Amasty\Flags\Controller\Adminhtml\Flag\Save
{
    /**
     * @var Context
     */
    private $context;
    /**
     * @var FlagRepositoryInterface
     */
    private $flagRepository;
    /**
     * @var ColumnRepositoryInterface
     */
    private $columnRepository;
    /**
     * @var UploaderFactory
     */
    private $uploaderFactory;
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * Save Constructor
     *
     * @param Context $context
     * @param FlagRepositoryInterface $flagRepository
     * @param ColumnRepositoryInterface $columnRepository
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     */
    public function __construct(
        Context $context,
        FlagRepositoryInterface $flagRepository,
        ColumnRepositoryInterface $columnRepository,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem
    ) {
        parent::__construct(
            $context,
            $flagRepository,
            $columnRepository,
            $uploaderFactory,
            $filesystem
        );
        $this->context = $context;
        $this->flagRepository = $flagRepository;
        $this->columnRepository = $columnRepository;
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
    }

    /**
     * Save flag function
     *
     * @return Redirect
     */
    public function aroundExecute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $data = $this->getRequest()->getPostValue();
        if ($data) {
            $id = (int)$this->getRequest()->getParam('id');
            /** @var Flag $model */
            $model = $this->flagRepository->get($id);
            if (!$model->getId() && $id) {
                $this->messageManager->addErrorMessage(__('This flag no longer exists.'));
                return $resultRedirect->setPath('*/*/');
            }

            foreach (['apply_comment','apply_store','apply_status', 'apply_shipping', 'apply_payment'] as $field) {
                if (isset($data[$field])) {
                    $data[$field] = implode(',', $data[$field]);
                } else {
                    $data[$field] = '';
                }
            }

            $model->setData($data);
            if (!$data['apply_column']) {
                $model->unsetData('apply_column');
            }

            try {
                $this->flagRepository->save($model);

                if ($data['apply_column']) {
                    /** @var Column $column */
                    $column = $this->columnRepository->get($data['apply_column']);
                    $column->assignFlags([$model->getId()]);
                }

                try {
                    /** @var $uploader Uploader */
                    $uploader = $this->uploaderFactory->create(
                        ['fileId' => 'image_name']
                    );
                    $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png', 'svg']);

                    $directoryWrite = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);

                    if ($model->getImageName()) {
                        $directoryWrite->delete($model->getImageRelativePath());
                    }

                    $basePath = $directoryWrite->getAbsolutePath(Flag::FLAGS_FOLDER);
                    $imageName = "{$model->getId()}.{$uploader->getFileExtension()}";

                    $uploader->save($basePath, $imageName);

                    $model
                        ->setImageName($imageName)
                        ->save();

                } catch (Exception $e) {
                    if ($e->getCode() != Uploader::TMP_NAME_EMPTY) {
                        throw $e;
                    }
                }

                $this->messageManager->addSuccessMessage(__('The flag has been saved.'));

                $this->_session->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
                }

                return $resultRedirect->setPath('*/*/');
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $this->_session->setFormData($data);

                return $resultRedirect->setPath('*/*/edit', [
                    'id' => $this->getRequest()->getParam('id')
                ]);
            }
        }
        return $resultRedirect->setPath('*/*/');
    }
}

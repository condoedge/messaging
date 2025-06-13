<?php 

/* ACTIONS */

/* ELEMENTS */
function _FileUploadLinkAndBox($name, $toggleOnLoad = true, $fileIds = [], $maxFilesSize = null)
{
    $panelId = 'file-upload-'.uniqid();

    return [

        _Flex(
            _Link()->icon(_Sax('paperclip-2'))->class('text-level1 text-2xl')
                ->balloon('attach-files', 'up')
                ->toggleId($panelId, $toggleOnLoad),
            _Html()->class('text-xs text-gray-700 font-semibold')->id('file-size-div')
        ),

        _Rows(
            _FlexBetween(
                _Rows(
                    _MultiFile()->placeholder('messaging-browse-files')->name($name)->class('mb-0')
                        ->id('email-attachments-input')->run('calculateTotalFileSize'),
                    !$maxFilesSize ? null : _Html(__('translate.with-values.max-files-size-is', [
                        'size' => $maxFilesSize
                    ]))->class('text-xs text-gray-500 absolute -bottom-5'),
                )->class('relative w-full md:w-5/12'),
                _Html('messaging-or')
                    ->class('text-sm text-gray-700 my-2 md:my-0'),
                \Condoedge\Utils\Kompo\Files\FileLibraryAttachmentQuery::libraryFilesPanel($fileIds)
                    ->class('w-full md:w-5/12'),
            )->class('flex-wrap'),
            _Html('messaging-your-files-exceed-max-size')
                ->class('hidden text-danger text-xs')->id('file-size-message')
        )->class('mx-2 dashboard-card p-2 space-x-2')
        ->id($panelId)

    ];
}

function attachmentsValidTypes()
{
    if (app()->has('attachment-valid-types')) {
        return app('attachment-valid-types');
    }

    return ['jpg','jpeg','png','gif','doc','docx','pdf','txt','zip','rar','xlsx','xls','csv','ppt','pptx'];
}
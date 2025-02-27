<?php 

/* ACTIONS */

/* ELEMENTS */
function _FileUploadLinkAndBox($name, $toggleOnLoad = true, $fileIds = [])
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
                _MultiFile()->placeholder('browse-files')->name($name)->class('mb-0 w-full md:w-5/12')
                    ->id('email-attachments-input')->run('calculateTotalFileSize'),
                _Html('or')
                    ->class('text-sm text-gray-700 my-2 md:my-0'),
                \Kompo\Auth\Files\FileLibraryAttachmentQuery::libraryFilesPanel($fileIds)
                    ->class('w-full md:w-5/12'),
            )->class('flex-wrap'),
            _Html('file.your-files-exceed-max-size')
                ->class('hidden text-danger text-xs')->id('file-size-message')
        )->class('mx-2 dashboard-card p-2 space-x-2')
        ->id($panelId)

    ];
}
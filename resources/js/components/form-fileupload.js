/*
Template Name: Lahomes - Real Estate Admin Dashboard Template
Author: Techzaa
File: form - Dropzone js
*/
import Dropzone from 'dropzone/dist/dropzone'
import { configureUploadDelete } from './delete-confirmation';

configureUploadDelete(Dropzone);


// Dropzone
var dropzonePreviewNode = document.querySelector("#dropzone-preview-list");
if (dropzonePreviewNode) {
    dropzonePreviewNode.id = "";
    var previewTemplate = dropzonePreviewNode.parentNode.innerHTML;
    dropzonePreviewNode.parentNode.removeChild(dropzonePreviewNode);
    var dropzone = new Dropzone(".dropzone", {
        url: 'https://httpbin.org/post',
        method: "post",
        previewTemplate: previewTemplate,
        previewsContainer: "#dropzone-preview",
        dictRemoveFileConfirmation: 'Hapus file ini dari daftar unggahan?',
        dictCancelUploadConfirmation: 'Batalkan unggahan file ini?',
    });
}

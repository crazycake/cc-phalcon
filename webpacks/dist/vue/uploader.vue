/**
 * Uploader component for CrazyCake Phalcon App */
 * Requires crazycake JS core
 */

//SCSS
<style lang="scss">

	@import "sass-md-colors/colors/variables";

	div.upload-container {
		ul {
			margin: 0;
			list-style: none;
			a { padding-left: 0.75rem; } /* 12/16 */
			span { font-size: 0.875rem; } /* 14/16 */
		}
		input[type="file"] {
			display: block;
		    width: 100%;
		    padding: 0.5rem 0.75rem;
			font-size: 0.875rem; /* 14/16 */
		    line-height: 1.25;
		    background-color: #fff;
		    background-clip: padding-box;
		    border: 1px solid #eceeef;
		    border-radius: 0.25rem;
			color: #666
		}
		//auto-upload for single files
		div.single button { display: none; }
		//Uploader component progress bar
		div.progress-bar {
			width: 0;
			height: 0.25rem; /* 4/16 */
			margin: 0.375rem 0; /* 6/16 */
			background: $light-green-600;
			border-radius: 6px;
			opacity: 1;
		}
		//image holder for uploaded ones
		span.image-holder {
			display: inline-block;
			cursor: pointer;
			img { height: 40px; }
			a {
				opacity: 0;
				position: relative;
				right: 30px;
				color: #fff;
				transition: opacity .2s ease-in-out;
			   	-moz-transition: opacity .2s ease-in-out;
			   	-webkit-transition: opacity .2s ease-in-out;
			}
			&:hover a { opacity: 100; }
		}
	}
</style>

//HTML
<template>
	<div class="upload-container">
		<!-- vue-upload-component -->
		<file-upload class="single" :name="getName" :class="getStateClass"
					 :action="getUploadAction" :headers="getHeaders">
		</file-upload>
		<!-- validator -->
		<input name="uploader-validator" type="hidden" v-if="required"
			   data-fv-excluded="false" data-fv-required="numeric : {}"
			   data-fv-message="Selecciona una imagen." />
		<!-- progress bar -->
		<div class="progress-bar" v-show="file_progress > 0" :style="getProgressBarStyle"></div>
		<!-- files uploaded -->
		<ul class="list-group" v-show="uploaded_files.length > 0">
			<!-- loop through the completed files -->
			<li class="list-group-item" v-for="(index,file) in uploaded_files">
				<span class="tag tag-warning" v-text="file.name"></span>
				<span class="tag tag-default" v-text="file.size | prettyBytes"></span>
				<a href="javascript:void(0);" @click="removeUpload(index)">eliminar</a>
			</li>
		</ul>
		<!-- uploader messages -->
		<small class="form-error" v-if="messages" v-text="messages"></small>
		<!-- image uploaded box -->
		<span v-if="imageUrl.length && !uploaded_files.length" @click="imageZoom" class="image-holder">
			<img :src="imageUrl" alt="" />
			<a href="javascript:void(0);">
				<i class="material-icons md-32">zoom_in</i>
			</a>
		</span>
	</div>
</template>

//JS: NOTE only one file upload supported for now.
//Lodash required.
<script>

//imports
import "vue-file-upload";
import "vue-pretty-bytes";

export default {
	props: {
		name   	   : String, //the input name (key)
		controller : String, //url action
		imageUrl   : String, //image holder for current image
		required   : false  //required prop for form validation
	},
	data() {
		return {
			uploaded_files : [],    //uploaded files
			file_progress  : 0,     //global progress
			messages       : false  //response messages
		};
	},
	ready() {

		//add style class for label
		$(this.$el).find("label").addClass("file");

		//set event for childs
		for (let child of this.$children)
			child.$on("uploader:upload", function() { this.fileUpload(); });
	},
	computed : {
		getName() {

			return _.uniqueId(this.name);
		},
		getHeaders() {

			return { "File-Key" : this.name };
		},
		getStateClass() {

			return this.uploaded_files.length ? "finished" : "pending";
		},
		getProgressBarStyle() {

			return { width : this.file_progress + "%" };
		},
		getUploadAction() {

			return core.baseUrl(this.controller + "/upload");
		}
	},
	methods : {
		//remove file upload
		removeUpload(index) {

			var self = this;

			let uploaded_file = self.uploaded_files[index];

			core.ajaxRequest({ method : "POST", uri : this.controller + "/removeUploadedFile" }, null, { uploaded_file : uploaded_file })
			.then(function(payload) {

				if(!payload)
					return;

				//remove object from array
				self.uploaded_files.splice(index, 1);

				//re-enable input
				$('input[type="file"]', self.$el).val("").prop("disabled", false);

				//hidden validator
				self.checkValidator();
			});
		},
		//core formValidator
		checkValidator() {

			let sel = $('input[name="uploader-validator"]', this.$el);

			if(!sel.length)
				return;

			//set value
			sel.val(this.uploaded_files.length ? "1" : "");
			//revalidate field
			core.modules.forms.revalidateField("uploader-validator");
		},
		//image zoom
		imageZoom() {

			let w = $(window).width()*0.8;
			let h = $(window).height()*0.8;

			//open image in a new window
			window.open(this.imageUrl, "_blank", "menubar=no, width="+w+", height="+h+", left=100, top=100", true);
		}
	},
	/*eslint-disable no-unused-vars*/
	events : {
		//reload all files uploaded
		"uploader:reload"() {

			for (var i = 0; i < this.uploaded_files.length; i++)
				this.removeUpload(i);
		},
		//VueUploader events
		onFileClick(file) {

			//console.log("Uploader -> onFileClick:", file);
		},
		onFileChange(file) {

			//console.log("Uploader -> onFileChange:", file);

			//check if file already exists
			//var filter = this.uploaded_files.filter(o => o.name == file.name);

			//update the view
			this.file_progress = 0;

			//trigger event
			this.$broadcast("uploader:upload");
		},
		beforeFileUpload(file) {

			//called when the upload handler is called
			//console.log("Uploader -> beforeFileUpload:", file);
		},
		afterFileUpload(file) {

			//called after the xhr.send() at the end of the upload handler
			//console.log("Uploader -> afterFileUpload:", file);
		},
		onFileProgress(progress) {

			var self = this;

			// update view progress bar
			self.file_progress = progress.percent;

			if(self.file_progress >= 100)
				setTimeout(function() { self.file_progress = 0; }, 500);
		},
		onFileUpload(file, res) {

			//check for errors
			if(res.response.payload.errors.length) {

				this.checkValidator();
				this.messages = res.response.payload.errors.join("<br/>");
				return;
			}

			for (let uploaded of res.response.payload.uploaded) {
				if(APP.dev) { console.log("Uploader -> Ok file uploaded", uploaded); }
				//push
				this.uploaded_files.push(uploaded);
			}

			//clean messages
			this.messages = "";
		},
		onFileError(file, res) {

			if(APP.dev) { console.log("Uploader -> onFileError:", file, res); }
		},
		onAllFilesUploaded(files) {

			//console.log("Uploader -> onAllFilesUploaded:", files);

			//hidden validation
			this.checkValidator();

			if(!this.uploaded_files.length)
				return;

			//disable file
			$('input[type="file"]', this.$el).prop("disabled", true);
		}
	}
};
</script>

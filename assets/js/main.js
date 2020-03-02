jQuery(document).ready(function($){
    /* courses */
    if($( "body" ).hasClass( "single-courses")){
        $('a.complete-lesson').on('click',function(e){
            e.preventDefault();
            var lessonId = $(this).data('lesson-id');
            var courseId = $(this).data('course-id');
            var url = acf.data.ajaxurl;
            $.ajax({
            type: 'post',
            url: url,
            data: {
                action : 'updateStatus',
                lesson : {lessonId:lessonId,courseId:courseId}
            },
            success: function(data, textStatus){
                if($('.is-active').next().find('.lesson').length){
                    var link = $('.is-active').next().find('.lesson').attr('href');
                    var hr = window.location.origin+window.location.pathname+link;
                    $(location).attr('href', hr);
                }
                else{
                    if(data == 0) {
                        document.location.reload(true);
                    }
                    else {
                        var hr = window.location.origin+window.location.pathname+'?lesson=finished';
                        $(location).attr('href', hr);
                    }          
                }
            } 
        });
        })
    }
});
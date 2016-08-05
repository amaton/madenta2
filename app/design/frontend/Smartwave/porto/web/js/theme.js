/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
require([
    'jquery',
    'mage/smart-keyboard-handler',
    'mage/mage',
    'mage/ie-class-fixer',
    'domReady!'
], function ($, keyboardHandler) {
    'use strict';
    $(document).ready(function(){
        $('.cart-summary').mage('sticky', {
            container: '#maincontent'
        });

        $('.panel.header .header.links').clone().appendTo('#store\\.links');
    });
    keyboardHandler.apply();
});
/******************** Init Parallax ***********************/
require([
    'jquery',
    'js/jquery.stellar.min'
], function ($) {
    $(document).ready(function(){
        $(window).stellar({
            responsive: true,
            scrollProperty: 'scroll',
            parallaxElements: false,
            horizontalScrolling: false,
            horizontalOffset: 0,
            verticalOffset: 0
        });
    });
});

require([
    'jquery'
], function ($) {
    (function() {
        var ev = new $.Event('classadded'),
            orig = $.fn.addClass;
        $.fn.addClass = function() {
            $(this).trigger(ev, arguments);
            return orig.apply(this, arguments);
        }
    })();
    $(document).ready(function(){
        var windowScroll_t;
        $(window).scroll(function(){
            clearTimeout(windowScroll_t);
            windowScroll_t = setTimeout(function(){
                if(jQuery(this).scrollTop() > 100){
                    $('#totop').fadeIn();
                }else{
                    $('#totop').fadeOut();
                }
            }, 500);
        });
        $('#totop').off("click").on("click",function(){
            $('html, body').animate({scrollTop: 0}, 600);
        });
        if ($('body').hasClass('checkout-cart-index')) {
            if ($('#co-shipping-method-form .fieldset.rates').length > 0 && $('#co-shipping-method-form .fieldset.rates :checked').length === 0) {
                $('#block-shipping').on('collapsiblecreate', function () {
                    $('#block-shipping').collapsible('forceActivate');
                });
            }
        }
        $(".word-rotate").each(function() {

            var $this = $(this),
                itemsWrapper = $(this).find(".word-rotate-items"),
                items = itemsWrapper.find("> span"),
                firstItem = items.eq(0),
                firstItemClone = firstItem.clone(),
                itemHeight = 0,
                currentItem = 1,
                currentTop = 0;

            itemHeight = firstItem.height();

            itemsWrapper.append(firstItemClone);

            $this
                .height(itemHeight)
                .addClass("active");

            setInterval(function() {
                currentTop = (currentItem * itemHeight);
                
                itemsWrapper.animate({
                    top: -(currentTop) + "px"
                }, 300, function() {
                    currentItem++;
                    if(currentItem > items.length) {
                        itemsWrapper.css("top", 0);
                        currentItem = 1;
                    }
                });
                
            }, 2000);

        });
        
        $(".search-toggle-icon").click(function(e){
            if($(this).parent().children(".block-search").hasClass("show")) {
                $(this).parent().children(".block-search").removeClass("show");
            } else {
                $(this).parent().children(".block-search").addClass("show");
            }
            e.stopPropagation();
        });
        $(".search-toggle-icon").parent().click(function(e){
            e.stopPropagation();
        });
        $("html,body").click(function(){
            $(".search-toggle-icon").parent().children(".block-search").removeClass("show");
        });
        
        /********************* Qty Holder **************************/
        $(".qty-inc").unbind('click').click(function(){
            if($(this).parent().parent().children(".control").children("input.input-text.qty").is(':enabled')){
                $(this).parent().parent().children(".control").children("input.input-text.qty").val((+$(this).parent().parent().children(".control").children("input.input-text.qty").val() + 1) || 0);
                $(this).parent().parent().children(".control").children("input.input-text.qty").trigger('change');
                $(this).focus();
            }
        });
        $(".qty-dec").unbind('click').click(function(){
            if($(this).parent().parent().children(".control").children("input.input-text.qty").is(':enabled')){
                $(this).parent().parent().children(".control").children("input.input-text.qty").val(($(this).parent().parent().children(".control").children("input.input-text.qty").val() - 1 > 0) ? ($(this).parent().parent().children(".control").children("input.input-text.qty").val() - 1) : 0);
                $(this).parent().parent().children(".control").children("input.input-text.qty").trigger('change');
                $(this).focus();
            }
        });
        
        /********** Fullscreen Slider ************/
        var s_width = $(window).innerWidth();
        var s_height = $(window).innerHeight();
        var s_ratio = s_width/s_height;
        var v_width=320;
        var v_height=240;
        var v_ratio = v_width/v_height;
        $(".full-screen-slider div.item").css("position","relative");
        $(".full-screen-slider div.item").css("overflow","hidden");
        $(".full-screen-slider div.item").width(s_width);
        $(".full-screen-slider div.item").height(s_height);
        $(".full-screen-slider div.item > video").css("position","absolute");
        $(".full-screen-slider div.item > video").bind("loadedmetadata",function(){
            v_width = this.videoWidth;
            v_height = this.videoHeight;
            v_ratio = v_width/v_height;
            if(s_ratio>=v_ratio){
                $(this).width(s_width);
                $(this).height("");
                $(this).css("left","0px");
                $(this).css("top",(s_height-s_width/v_width*v_height)/2+"px");
            }else{
                $(this).width("");
                $(this).height(s_height);
                $(this).css("left",(s_width-s_height/v_height*v_width)/2+"px");
                $(this).css("top","0px");
            }
            $(this).get(0).play();
        });
        if($(".page-header").hasClass("type10")) {
            if(s_width >= 992){
                $(".navigation").addClass("side-megamenu")
            } else {
                $(".navigation").removeClass("side-megamenu")
            }
        }
        
        $(window).resize(function(){
            s_width = $(window).innerWidth();
            s_height = $(window).innerHeight();
            s_ratio = s_width/s_height;
            $(".full-screen-slider div.item").width(s_width);
            $(".full-screen-slider div.item").height(s_height);
            $(".full-screen-slider div.item > video").each(function(){
                if(s_ratio>=v_ratio){
                    $(this).width(s_width);
                    $(this).height("");
                    $(this).css("left","0px");
                    $(this).css("top",(s_height-s_width/v_width*v_height)/2+"px");
                }else{
                    $(this).width("");
                    $(this).height(s_height);
                    $(this).css("left",(s_width-s_height/v_height*v_width)/2+"px");
                    $(this).css("top","0px");
                }
            });
            if($(".page-header").hasClass("type10")) {
                if(s_width >= 992){
                    $(".navigation").addClass("side-megamenu")
                } else {
                    $(".navigation").removeClass("side-megamenu")
                }
            }
        });
        var breadcrumb_pos_top = 0;
        $(window).scroll(function(){
            if(!$("body").hasClass("cms-index-index")){
                var side_header_height = $(".page-header.type10").innerHeight();
                var window_height = $(window).height();
                if(side_header_height-window_height<$(window).scrollTop()){
                    if(!$(".page-header.type10").hasClass("fixed-bottom"))
                        $(".page-header.type10").addClass("fixed-bottom");
                }
                if(side_header_height-window_height>=$(window).scrollTop()){
                    if($(".page-header.type10").hasClass("fixed-bottom"))
                        $(".page-header.type10").removeClass("fixed-bottom");
                }
            }
            if($("body.side-header .page-wrapper > .breadcrumbs").length){
                if(!$("body.side-header .page-wrapper > .breadcrumbs").hasClass("fixed-position")){
                    breadcrumb_pos_top = $("body.side-header .page-wrapper > .breadcrumbs").offset().top;
                    if($("body.side-header .page-wrapper > .breadcrumbs").offset().top<$(window).scrollTop()){
                        $("body.side-header .page-wrapper > .breadcrumbs").addClass("fixed-position");
                    }
                }else{
                    if($(window).scrollTop()<=1){
                        $("body.side-header .page-wrapper > .breadcrumbs").removeClass("fixed-position");
                    }
                }
            }
        });
    });
});
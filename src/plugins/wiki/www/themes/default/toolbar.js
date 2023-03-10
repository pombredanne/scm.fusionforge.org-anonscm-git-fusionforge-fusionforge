// Toolbar JavaScript support functions. Taken from mediawiki

// Some "constants"
var doctype = '<!DOCTYPE html>';
var cssfile = '<link rel="stylesheet" type="text/css" href="'+data_path+'/themes/default/toolbar.css" />';

// Un-trap us from framesets
if( window.top != window ) window.top.location = window.location;
var pullwin;

// This function generates a popup list to select from.
//   plugins, pagenames, categories, templates.
// Not with document.write because we cannot use self.opener then.
// pages is either an array of strings or an array of array(name,value)
function showPulldown(title, pages, okbutton, closebutton, fromid) {
  var height = new String(Math.min(315, 80 + (pages.length * 12))); // 270 or smaller
  var width = 500;
  var h = (screen.height-height)/2;
  var w = (screen.width-width)/2;
  pullwin = window.open('','','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=no,resizable=yes,copyhistory=no,top='+h+',left='+w+',height='+height+',width='+width);
   // Close the window with Escape key
   pullwin.onkeydown = function(e){
       if(e.keyCode === 27){
           pullwin.window.close();
       }
   };
  pullwin.window.document.writeln(doctype);
  pullwin.window.document.writeln('<html xml:lang="en" lang="en" >\n<head>\n<title>'+escapeQuotes(title)+'</title>');
  pullwin.window.document.writeln(cssfile);
  pullwin.window.document.writeln('</head>\n<body>');
  pullwin.window.document.writeln('<p>\nYou can double-click to insert.\n</p>');
  pullwin.window.document.writeln('<form><div id=\"buttons\"><input type=\"button\" value=\"'+okbutton+'\" onclick=\"if(self.opener)self.opener.do_pulldown(document.forms[0].select.value,\''+fromid+'\'); return false;\" /><input type=\"button\" value=\"'+closebutton+'\" onclick=\"self.close(); return false;\" /></div>\n<div>\n<select style=\"margin-top:10px;width:190px;\" name=\"select\" size=\"'+((pages.length>20)?'20':new String(pages.length))+'\" ondblclick=\"if(self.opener)self.opener.do_pulldown(document.forms[0].select.value,\''+fromid+'\'); return false;\">');
  for (var i=0; i<pages.length; i++){
    if (typeof pages[i] == 'string')
      pullwin.window.document.write('<option value="'+pages[i]+'">'+escapeQuotes(pages[i])+'</option>\n');
    else  // array=object
      pullwin.window.document.write('<option value="'+pages[i][1]+'">'+escapeQuotes(pages[i][0])+'</option>\n');
  }
  pullwin.window.document.writeln('</select>\n</div>\n</form>\n</body>\n</html>');
  pullwin.window.document.close();
  return false;
}
function do_pulldown(text,fromid) {
    // do special actions dependent on fromid: tb-categories
    if (fromid == 'tb-categories') {
        var txtarea = document.getElementById('edit-content');
        text = unescapeSpecial(text);
        txtarea.value += '\n'+text;
    } else if (fromid == 'tb-templates') {
        text = text.replace(/__nl__/g, '\n');
        text = text.replace(/__quot__/g, '"');
        insertTags(text, '', '\n');
    } else {
        insertTags(text, '', '\n');
    }
}
function escapeQuotes(text) {
  var re=new RegExp("'","g");
  text=text.replace(re,"\\'");
  re=new RegExp('"',"g");
  text=text.replace(re,'&quot;');
  re=new RegExp("\\n","g");
  text=text.replace(re,"\\n");
  return text;
}
function unescapeSpecial(text) {
    // IE
    var re=new RegExp('%0A',"g");
    text = text.replace(re,'\n');
    re=new RegExp('%22',"g");
    text = text.replace(re,'"');
    re=new RegExp('%27',"g");
    text = text.replace(re,'\'');
    re=new RegExp('%09',"g");
    text = text.replace(re,'    ');
    re=new RegExp('%7C',"g");
    text = text.replace(re,'|');
    re=new RegExp('%5B',"g");
    text = text.replace(re,'[');
    re=new RegExp('%5D',"g");
    text = text.replace(re,']');
    re=new RegExp('%5C',"g");
    text = text.replace(re,'\\');
    return text;
}

// apply tagOpen/tagClose to selection in textarea,
// use sampleText instead of selection if there is none
// copied and adapted from phpBB
function insertTags(tagOpen, tagClose, sampleText) {
  //f=document.getElementById('editpage');
  var txtarea = document.getElementById('edit-content');
  // var txtarea = document.editpage.edit[content];
  tagOpen = unescapeSpecial(tagOpen);

  if(document.selection) {
    var theSelection = document.selection.createRange().text;
    if(!theSelection) { theSelection=sampleText;}
    txtarea.focus();
    if(theSelection.charAt(theSelection.length - 1) == " "){// exclude ending space char, if any
      theSelection = theSelection.substring(0, theSelection.length - 1);
      document.selection.createRange().text = tagOpen + theSelection + tagClose + " ";
    } else {
      document.selection.createRange().text = tagOpen + theSelection + tagClose;
    }
    // Mozilla -- this induces a scrolling bug which makes it virtually unusable
  } else if(txtarea.selectionStart || txtarea.selectionStart == '0') {
    var startPos = txtarea.selectionStart;
    var endPos = txtarea.selectionEnd;
    var scrollTop=txtarea.scrollTop;
    var myText = (txtarea.value).substring(startPos, endPos);
    if(!myText) { myText=sampleText;}
    var subst;
    if(myText.charAt(myText.length - 1) == " "){ // exclude ending space char, if any
      subst = tagOpen + myText.substring(0, (myText.length - 1)) + tagClose + " ";
    } else {
      subst = tagOpen + myText + tagClose;
    }
    txtarea.value = txtarea.value.substring(0, startPos) + subst + txtarea.value.substring(endPos, txtarea.value.length);
    txtarea.focus();
    var cPos=startPos+(tagOpen.length+myText.length+tagClose.length);
    txtarea.selectionStart=cPos;
    txtarea.selectionEnd=cPos;
    txtarea.scrollTop=scrollTop;
    // All others
  } else {
    // Append at the end: Some people find that annoying
    txtarea.value += tagOpen + sampleText + tagClose;
    //txtarea.focus();
    //var re=new RegExp("\\n","g");
    //tagOpen=tagOpen.replace(re,"");
    //tagClose=tagClose.replace(re,"");
    //document.infoform.infobox.value=tagOpen+sampleText+tagClose;
    txtarea.focus();
  }
  // reposition cursor if possible
  if (txtarea.createTextRange) txtarea.caretPos = document.selection.createRange().duplicate();
}

function convert_tab_to_table() {

    // obtain the object reference for the <textarea>
    var txtarea = document.getElementById('edit-content');
    // obtain the index of the first selected character
    var start = txtarea.selectionStart;
    // obtain the index of the last selected character
    var finish = txtarea.selectionEnd;
    //obtain all Text
    var allText = txtarea.value;
    // obtain the selected text
    var theSelection = allText.substring(start, finish);

    // replace tabs by pipe surrounded by spaces
    theSelection = theSelection.replace(/\t/g, ' | ');
    // add pipe followed by space at beginning of lines
    theSelection = theSelection.replace(/^/g, '| ');
    theSelection = theSelection.replace(/\n/g, '\n| ');

    // append the text
    var newText=allText.substring(0, start)+theSelection+allText.substring(finish, allText.length);
    txtarea.value=newText;
}

// JS_SEARCHREPLACE from walterzorn.de
var f, sr_undo, replacewin, undo_buffer=new Array(), undo_buffer_index=0;

function define_f() {
   f=document.getElementById('editpage');
   f.editarea=document.getElementById('edit-content');
   sr_undo=document.getElementById('sr_undo');
   undo_enable(false);
   f.editarea.focus();
}
function undo_enable(bool) {
   if (bool) {
     sr_undo.src=uri_undo_btn;
     sr_undo.alt=msg_undo_alt;
     sr_undo.disabled = false;
   } else {
       sr_undo.src=uri_undo_d_btn;
       sr_undo.alt=msg_undo_d_alt;
       sr_undo.disabled = true;
       if(sr_undo.blur) sr_undo.blur();
   }
}
function replace() {
   var height = 120;
   var width = 600;
   var h = (screen.height-height)/2;
   var w = (screen.width-width)/2;
   replacewin = window.open('','','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=no,resizable=yes,copyhistory=no,top='+h+',left='+w+',height='+height+',width='+width);
   // Close the window with Escape key
   replacewin.onkeydown = function(e){
       if(e.keyCode === 27){
           replacewin.window.close();
       }
   };
   replacewin.window.document.writeln(doctype);
   replacewin.window.document.writeln('<html>\n<head>\n<title>'+msg_repl_title+'</title>');
   replacewin.window.document.writeln(cssfile);
   replacewin.window.document.writeln('</head>');
   replacewin.window.document.writeln("<body onload=\"if(document.forms[0].searchinput.focus) document.forms[0].searchinput.focus(); return false;\">\n<form action=\"\">\n<center>\n<table>\n<tr>\n<td align=\"right\">"+msg_repl_search+":\n</td>\n<td align=\"left\">\n<input type=\"text\" name=\"searchinput\" size=\"45\" maxlength=\"500\" />\n</td>\n</tr>\n<tr>\n<td align=\"right\">"+msg_repl_replace_with+":\n</td>\n<td align=\"left\">\n<input type=\"text\" name=\"replaceinput\" size=\"45\" maxlength=\"500\" />\n</td>\n</tr>\n<tr>\n<td colspan=\"2\" align=\"center\">\n<input type=\"button\" value=\" "+msg_repl_ok+" \" onclick=\"if(self.opener)self.opener.do_replace(); return false;\" />&nbsp;&nbsp;&nbsp;<input type=\"button\" value=\""+msg_repl_close+"\" onclick=\"self.close(); return false;\" />\n</td>\n</tr>\n</table>\n</center>\n</form>\n</body>\n</html>");
   replacewin.window.document.close();
   return false;
}
function do_replace() {
   var txt = undo_buffer[undo_buffer_index]=f.editarea.value;
   var searchinput = new RegExp(replacewin.document.forms[0].searchinput.value,'g');
   var replaceinput = replacewin.document.forms[0].replaceinput.value;
   if (searchinput==''||searchinput==null) {
      if (replacewin) replacewin.window.document.forms[0].searchinput.focus();
      return;
   }
   var z_repl=txt.match(searchinput)? txt.match(searchinput).length : 0;
   txt=txt.replace(searchinput,replaceinput);
   searchinput=searchinput.toString().substring(1,searchinput.toString().length-2);
   msg_replfound = msg_replfound.replace('\1', searchinput).replace('\2', z_repl).replace('\3', replaceinput);
   msg_replnot = msg_replnot.replace('%s', searchinput);
   result(z_repl, msg_replfound, txt, msg_replnot);
   replacewin.window.focus();
   replacewin.window.document.forms[0].searchinput.focus();
   return false;
}
function result(count,question,value_txt,alert_txt) {
   if (count>0) {
      if(window.confirm(question)==true) {
         f.editarea.value=value_txt;
         undo_save();
         undo_enable(true);
      }
   } else {
       alert(alert_txt);
   }
}
function do_undo() {
   if(undo_buffer_index>0) {
      f.editarea.value=undo_buffer[undo_buffer_index-1];
      undo_buffer[undo_buffer_index]=null;
      undo_buffer_index--;
      if(undo_buffer_index==0) {
         alert(msg_do_undo);
         undo_enable(false);
      }
   }
}
//save a snapshot in the undo buffer
function undo_save() {
   undo_buffer[undo_buffer_index]=f.editarea.value;
   undo_buffer_index++;
   undo_enable(true);
}

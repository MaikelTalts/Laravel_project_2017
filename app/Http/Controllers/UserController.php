<?php

namespace Filebrowser\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Filebrowser\Activity;
use Filebrowser\User;
use Filebrowser\Folder;

class UserController extends Controller
{
  public function updateStatus(Request $request){
    //Get the current logged in user's name for activity insert
    $currentUserName = Auth::user()->name;
    //Get selected user
    $userID = $request['userID'];
    $user = User::find($userID);
    $userName = $user->name;
    //Get selection
    $selection = $request['statusSelection'];
    //Update selected users privilege status.
    User::where('id', $userID)->update(['user_privileges'=>$selection]);

    //Return response
    if($selection == 1){
      $newUserStatus = "User";
      $notificationText = $userName . " " . "is now a user";
      app('Filebrowser\Http\Controllers\ActivityController')->updateActivityLog($currentUserName, "changed", "user", $userName, "as", "user");
    }
    else{
      $newUserStatus = "<strong>Admin</strong>";
      $notificationText = $userName . " " . "is now an admin";
      app('Filebrowser\Http\Controllers\ActivityController')->updateActivityLog($currentUserName, "changed", "user", $userName, "as", "admin");
    }
    return response()->json([
      'notificationMessage' => $notificationText,
      'newUserStatus' => $newUserStatus,
      'success' => 'Succeed',
      'error'   => "Didn't work"
    ]);
  }

  public function deleteUser(Request $request){
    //Get the current logged in user's name for activity insert
    $currentUserName = Auth::user()->name;
    //Check witch user was selected
    $userID = $request['userID'];
    //Search user from database
    $user = User::find($userID);
    //Get the users name
    $userName = $user->name;
    //Insert new activity
    app('Filebrowser\Http\Controllers\ActivityController')->updateActivityLog($currentUserName, "deleted", "user", $userName, "", "");


    //Poistetaan folder_user tietokantataulusta kaikki en tietueet, joissa poistettava käyttäjä esiintyy
    DB::table('folder_user')->where('user_id', '=', $userID)->delete();
    //Delete selected user from database
    User::where('id', $userID)->delete();
    //Create response message to show in the notification bar
    $notificationText = $userName . " has been deleted succesfully";
    //Return response
    return response()->json([
      'notificationMessage' => $notificationText,
      'success' => 'User was deleted',
      'error'   => 'Error'
    ]);
  }

  function updateFolderPrivileges(Request $request) {
    //Request the base variables
    $userID = $request['userID'];
    $folderID = $request['folderID'];
    //Search the user from database
    $user = User::find($userID);
    //Get the users name
    $userName = $user->name;
    //Search the folder from database
    $folder = Folder::find($folderID);
    //Get the folders named
    $folderName = $folder->folder_name;
    //Search if there already is a row in folder_user table with selected user and selected folder
    $userFolderStatus = DB::table('folder_user')->where(
                            'user_id', '=', $userID)->where(
                            'folder_id', '=', $folderID)->first();
    //If user does not yet have access in selected folder remove the access.
    if($userFolderStatus == null){
        DB::table('folder_user')->insert(
          ['user_id' => $userID, 'folder_id' => $folderID]);
          //Write a notification message
          $notificationMsg = $userName. " now has access to " . $folderName;
    }
    //If user already has access to selected folder, remove it
    else{
      DB::table('folder_user')->where(
        'user_id', '=', $userID)->where(
        'folder_id', '=', $folderID)->delete();
        //Write a notification message
        $notificationMsg = $userName . " has no longer access to " . $folderName;
    }
    //Return response
    return response()->json([
                'notificationMsg' => $notificationMsg,
                'error' => "Didn't work"
    ]);
  }

  function updateUploadPrivilege(Request $request){
    //Request the base variables
    $userID = $request['userID'];
    $uploadSelection = $request['uploadSelection'];
    //Search the user from database
    $user = User::find($userID);
    //Get the users name
    $userName = $user->name;
    //Update users upload privileges
    User::where('id', $userID)->update(['user_upload_privilege' => $uploadSelection]);
    //Return response to ajax
    if($uploadSelection == 1){
      //Write a notification message
      $notificationMsg = $userName . " can no longer upload files";
    }
    else{
      //Write a notification message
      $notificationMsg = $userName . " can now upload files";
    }
    return response()->json([
                'notificationMsg' => $notificationMsg,
                'success' => "Changed",
                'error' => "Didn't work"
    ]);
  }

  function printUserPage(Request $request){
    //Request user's ID
    $userID = $request['userID'];

    $currentUserID = Auth::user()->id;
    if($userID == 1 && $currentUserID != 1 ){
        return response()->json([
          'denied' => true,
          'error' => "You do not have permission to admin settings!"
        ]);
    }
    //Select user that has the same id as requested
    $user = User::find($userID);
    //Select all folders that user has access to
    $userFolders = DB::select('select * from folder_user where user_id = ?', [$userID]);
    //Create array to add folder ID's
    $dirArray = array();
    //If $userFolders array contains info, push the folder id to array
    if($userFolders){
      foreach($userFolders as $folder){
        array_push($dirArray, $folder->folder_id);
      }
    }
    //Select all folders that has been listed in folders database table
    $directories = DB::select('select * from folders');
    //Create view to return for ajax
    $userPageContent = View::make('pages.userControlModal', ['user' => $user, 'dirArray' => $dirArray, 'directories' => $directories])->render();

    return response()->json([
      'success' => $userPageContent,
      'error' => "Didn't work"
    ]);
  }

  function updateUserInfo(Request $request){
    //Check the current user's id
    $currentUser = Auth::user()->id;
    $currentUserName = Auth::user()->name;
    //Request user's ID
    $userID = $request['userID'];
    if($currentUser == $userID){
      $sameUser = true;
    }
    else{
      $sameUser = false;
    }

    //Get user current name and the in input
    $oldUserName = $request['oldUserName'];
    $userNameInput = $request['userNameInput'];

    //Request user's email
    $userEmail = $request['userEmail'];
    //Trim spaces from beginning and end of string
    $userName = trim($userNameInput);
    if($userName != null && $userName != ""){
      if($oldUserName != $userNameInput){
        //Create new acitivity log mark
        app('Filebrowser\Http\Controllers\ActivityController')->updateActivityLog($currentUserName, "changed", "username", $oldUserName, "as", $userNameInput);

      }
      //Update selected user with requested data
      User::where('id', $userID)->update(['name' => $userName, 'email' => $userEmail]);
    }
    else{
      return response()->json([
        'changeFailed' => 'ChangeFailed',
        ]);
    }

    //Return response
    return response()->json([
      'newName' => $userName,
      'sameUser' => $sameUser,
      'success' => "Changed",
      'error' => "Failed"
    ]);
  }

  //Prints user settings page if user is not admin
  function showUserSettings(){
    //Check current user
    $user = Auth::user();
    //Get current user id
    $userID = $user->id;
    //Render user settings modal
    $userSettingsContent = View::make('pages.userSettings', ['user' => $user])->render();
    //Return modal content as response
    return response()->json([
      'success' => $userSettingsContent,
      'error' => "Didn't work"
    ]);
  }

  function updateUserPassword(Request $request){
    //Get the user id
    $userID = $request['userID'];
    //Get the password
    $password = $request['password'];
    //Crypt the password
    $passwordHash = bcrypt($password);
    //Replace the password where userID is the same as received one
    User::where('id', $userID)->update(['password' => $passwordHash]);
    //Return response to ajax
    return response()->json([
      'success' => 'Password changed',
      'error' => 'Password change failed'
    ]);
  }


}

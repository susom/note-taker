# NoteTaker
An EM that allows comments to be added to a single notes field, formatted with user and timestamp.

Useful examples include:
- Repeated casual observations
- Support notes or internal notes about a record
- Keeping logs for things like phone calls or follow-up attempts

Notes are arranged newest to oldest and prepended with a header with the following format:
```text
[username @ date] Note...
```

## Project Setup
To add Note Taker to a form, you need to create a minimum of three fields in a single event to track the note:
1. A Note Storage Field (text area or input).  This field will keep the growing note log.
  * This field is typically marked `@HIDDEN` and its value is displayed via piping.  An example of how one could
  pipe the value elsewhere on the form is:
  ```
  <div class="notesbox" style="border: 1px solid #ccc; overflow: auto; resize: auto; font-weight: normal;">[note_storage_field]</div>
  ```
  * Often we pipe the value of this field in the LABEL of the input field
1. An Note Entry/Input Field(s) (text area, input, dropdown, radio, or checkbox).  These fields will be added to the note.
  * This is the form where a user adds a value and then presses 'save' or 'save and stay' for the note to be applied.
  * In the most basic form, you have a single text area field for a comment.  In a more complex example around phone logging, you could
   have a radio field for the call outcome (e.g. busy, left a VM, left msg with other person, disconnected) along with a custom message
   and all values would be added to the log entry.  Obviously this isn't for data analysis so much as for convenient note taking.
1. A timestamp field (can be date or date/time) that will be updated with the time of the last update to the log.
  * This field is typically (e.g. `@HIDDEN-SURVEY` or `@HIDDEN`) or it can be marked as `@READONLY`.
  * The use of this field was for filtering reports or with a datediff and today for triggering for alerts and notifications.
  For example, send an alert or make a record appear in a report if it has been 14 days since my last log entry on this record.

> A sample [zip instrument is available here](NoteTakerExampleForm.zip) that can be uploaded to your project for quick setup. You can then move the fields to the instruments where you want them once configured.

After the fields are present, goto the External Module configuration page:

##Configuration options
The following instrument fields can be specified:
1. *[Arm & Event ID]* : The event where the fields above are configured
1. *[Note Storage Field]* : The field that contains all note contents (Supported Formats: text and notes)
1. *[Input Fields]* : One or more input fields which will be placed into the Storage field on each save.  (Supported Formats: Text/Notes/Radio/Dropdown/Checkbox)
1. *[Date Field]* : The date field that will be populated anytime a user edits the input field. Formats include date, datetime and datetime_seconds
1. *[Include a delimiter]* : If checked, will separate each valid record update with a string "------------------------------------------------------------"


##Notes:

###It is mandatory to choose a validation format when editing the date field of an instrument.
When choosing between validation formats for date in the designer, feel free to choose between the following options:
1. Date
1. Datetime
1. Datetime w/seconds

Note however that the header will be formatted in (Y-M-D) regardless of your choice. The only difference being the specificity rather than the order.
If specifying an altering order, the data will save in (Y-M-D) format, and you might receive an alert message upon record save.


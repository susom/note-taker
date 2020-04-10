# NoteTaker
An EM that allows comments to be added to a single notes field, formatted with user and timestamp.

Notes will be arranged newest to oldest and prepended with a header with the following format:
```text
[username @ date] Note...
```

## Project Setup
To add a log to a form, you need three fields:
1. An input field (or text area) where you will write your log entries
1. A timestamp field (can be date or date/time) that will be updated with the time of the last update to the log.
    * This field can be hidden (e.g. @HIDDEN-SURVEY or @HIDDEN) if you do not want to see the actual value on your form.
    * It is intended to be used for filtering reports or with a datediff and today for triggering for alerts and notifications.  For example, send an alert if it has been 14 days since my last log entry on this record.
1. A log field (typically a text area, but input will also work).  This is where the entire log is stored.
    * This field can also be marked as hidden and its value can be piped into another field, such as the notes of the input field.  The field can also be marked @READONLY to prevent manual log entries or deletions.

##Configuration options
The following instrument fields can be specified:
1. *[Arm & Event ID]* : The event where the records intended to be affected by this EM on are located
1. *[Input Field]* : The input instance you would like to pull text from to be added to the notes field
1. *[Date Field]* : The date field that will be populated anytime a user edits the input field. Formats include date, datetime and datetime_seconds
1. *[Notes Field]* : The field that contains all input field text separated by a header and optional delimiter
1. *[Include New Line]* : If checked, the new note will start on a new line
1. *[Include a delimiter]* : If checked, will separate each valid record update with a string "------------------------------------------------------------"


##Notes:
When choosing between validation formats for date in the designer, feel free to choose between the following options<br>
1. Date
1. Datetime
1. Datetime w/seconds

Note however that the header will be formatted in (Y-M-D) regardless of your choice. The only difference being the specificity rather than the order.
If specifying an altering order, the data will save in (Y-M-D) format, and you might receive an alert message upon record save.

##Additional Fields:

NoteTaker has the option of specifying additional fields beyond the designated input field to append to the Notes field.
The format for these fields will be outputted in the format : (fieldname=>value)

####Please Note that additional fields specified will not be added to the Notes field if the default Input field has no value.

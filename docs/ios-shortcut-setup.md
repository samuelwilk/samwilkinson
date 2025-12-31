# iOS Shortcut Setup for Bulk Photo Upload

## Configuration for Local Testing

**API Endpoints:**
- Collections list: `http://10.0.0.245:8000/api/photos/collections`
- Bulk upload: `http://10.0.0.245:8000/api/photos/bulk-upload`

**Bearer Token:** `c1efb029f3d85fdc13590a2577e6ac8a834323e54b1b251f79e05c9ed4ccfe78`

## Shortcut Flow Overview

The shortcut now supports two workflows:
1. **New Collection**: Enter name, choose draft/published status
2. **Existing Collection**: Select from list, photos automatically published

## Shortcut Steps

### 1. Select Photos
- **Action:** "Select Photos"
- **Select Multiple:** ON
- **Prompt:** "Select photos to upload"

### 2. Ask: New or Existing Collection?
- **Action:** "Choose from Menu"
- **Prompt:** "Upload to new or existing collection?"
- **Options:**
  - New Collection
  - Existing Collection

---

## Branch A: New Collection

### 3a. Ask for Collection Name
(Inside "New Collection" menu item)
- **Action:** "Ask for Input"
- **Prompt:** "Collection Name"
- **Default Answer:** "Untitled Collection"

### 4a. Ask for Publish Status
- **Action:** "Choose from Menu"
- **Prompt:** "Publish photos or save as draft?"
- **Options:**
  - Draft
  - Published

### 5a. Set Draft Status Variable
(Inside "Draft" menu item)
- **Action:** "Set Variable"
- **Variable Name:** `publishStatus`
- **Value:** `draft`

### 6a. Set Published Status Variable
(Inside "Published" menu item)
- **Action:** "Set Variable"
- **Variable Name:** `publishStatus`
- **Value:** `published`

### 7a. Set Collection ID Variable
- **Action:** "Set Variable"
- **Variable Name:** `collectionId`
- **Value:** `` (empty text)

### 8a. Set Collection Name Variable
- **Action:** "Set Variable"
- **Variable Name:** `collectionName`
- **Value:** [Provided Input] (from step 3a)

---

## Branch B: Existing Collection

### 3b. Fetch Collections List
(Inside "Existing Collection" menu item)
- **Action:** "Get Contents of URL"
- **URL:** `http://10.0.0.245:8000/api/photos/collections`
- **Method:** GET
- **Headers:**
  - Key: `Authorization`
  - Value: `Bearer c1efb029f3d85fdc13590a2577e6ac8a834323e54b1b251f79e05c9ed4ccfe78`

### 4b. Get Dictionary from Collections Response
- **Action:** "Get Dictionary from Input"
- **Input:** [Contents of URL]

### 5b. Get Collections Array
- **Action:** "Get value for key"
- **Key:** `collections`
- **Input:** [Dictionary]

### 6b. Choose Collection from Menu
- **Action:** "Choose from List"
- **Prompt:** "Select collection"
- **Input:** [Collections] (Get "name" from each item)
- **Note:** You'll need to manually build a menu or use a "Choose from List" with collection names

### 7b. Get Selected Collection ID
- **Action:** "Get value for key"
- **Key:** `id`
- **Input:** [Chosen Item]

### 8b. Set Collection ID Variable
- **Action:** "Set Variable"
- **Variable Name:** `collectionId`
- **Value:** [Collection ID from step 7b]

### 9b. Get Selected Collection Name
- **Action:** "Get value for key"
- **Key:** `name`
- **Input:** [Chosen Item]

### 10b. Set Collection Name Variable
- **Action:** "Set Variable"
- **Variable Name:** `collectionName`
- **Value:** [Collection Name from step 9b]

### 11b. Set Publish Status (empty for existing)
- **Action:** "Set Variable"
- **Variable Name:** `publishStatus`
- **Value:** `` (empty text - will default to "published")

---

## Common Steps (After Both Branches)

### 9. Set Photo Count Variable
- **Action:** "Set Variable"
- **Variable Name:** `photoCount`
- **Value:** `0` (number)

### 10. Repeat with Each Photo
- **Action:** "Repeat with Each"
- **Input:** [Selected Photos] (from step 1)
- **Repeat Actions:** (steps 11-14 go INSIDE the repeat block)

### 11. Get Contents of URL (API Request) - INSIDE REPEAT
- **Action:** "Get Contents of URL"
- **URL:** `http://10.0.0.245:8000/api/photos/bulk-upload`
- **Method:** POST
- **Headers:**
  - Key: `Authorization`
  - Value: `Bearer c1efb029f3d85fdc13590a2577e6ac8a834323e54b1b251f79e05c9ed4ccfe78`
- **Request Body:** Form
- **Form Fields:**
  - `photos[]` = [Repeat Item] (the current photo in the loop)
  - `collection_id` = [collectionId variable]
  - `collection_name` = [collectionName variable]
  - `publish_status` = [publishStatus variable] (empty for existing collections)

### 12. Get Dictionary from Input - INSIDE REPEAT
- **Action:** "Get Dictionary from Input"
- **Input:** [Contents of URL] (from step 11)

### 13. Set Variable (update collection ID) - INSIDE REPEAT
- **Action:** "Set Variable"
- **Variable Name:** `collectionId`
- **Value:** [Get value for "collection_id" in Dictionary] (from step 12)

### 14. Calculate (increment count) - INSIDE REPEAT
- **Action:** "Calculate"
- **Operation:** [photoCount] + 1
- **Then:** Set Variable `photoCount` to result

### 15. End Repeat
(This is automatic when you exit the repeat block)

### 16. Show Result
- **Action:** "Show Result"
- **Text:**
```
Upload Complete!

Photos uploaded: [photoCount]
Collection: [collectionName]
Collection ID: [collectionId]
```

## Common Issues & Fixes

### Issue 1: "Could not connect to server"
- **Check:** Are you on the same WiFi network as your Mac?
- **Check:** Is Docker running? (`docker compose ps`)
- **Test:** Can you access http://10.0.0.245:8000/api/photos/health in Safari?

### Issue 2: "401 Unauthorized"
- **Check:** Bearer token is correctly pasted (no extra spaces)
- **Check:** Token hasn't been deleted from database

### Issue 3: "400 Bad Request"
- **Check:** Form fields are named exactly: `photos[]`, `collection_name`, `publish_status`
- **Check:** Photos are being passed as array (note the `[]` in `photos[]`)

### Issue 4: Photos selected but form field empty
- **Fix:** In the "Get Contents of URL" action:
  - Tap the `photos[]` field
  - Tap "Select Variable"
  - Choose "Selected Photos" from step 1
  - Make sure it shows as a blue bubble, not text

### Issue 5: "publish_status" not being set
- **Check:** The menu items (Draft/Published) each have a "Set Variable" action
- **Check:** The variable name is exactly `publishStatus` (no spaces)
- **Check:** The form field references this variable (blue bubble)

## Testing the Shortcut

1. **Test Health Endpoint First**
   - Open Safari on your iPhone
   - Go to: `http://10.0.0.245:8000/api/photos/health`
   - Should see: `{"status":"ok","timestamp":"..."}`

2. **Run the Shortcut**
   - Select 1 photo for first test
   - Enter collection name: "Test Upload"
   - Choose "Draft"
   - Should see success message with "status": "processing"

3. **Check Admin Panel**
   - On your Mac, go to: http://localhost:8000/admin
   - Navigate to Collections
   - Look for "Test Upload" collection

## For Production (Later)

Replace local IP with your production domain:
- URL: `https://yourdomain.com/api/photos/bulk-upload`
- Generate new token for production use

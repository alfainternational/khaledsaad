# Content Attachments Design

## Goal

Allow an administrator to attach multiple downloadable files and external links to any article, lesson, lecture, or course from the existing content editor. These resources appear as an ordered "Accompanying materials" section on the public content page and inherit the content's public or email-subscriber access rule.

## Data model

Create `content_resources` as a normalized ordered child of `contents`. A resource has a `type` of `file` or `link`, a required display title, an optional `content_media_id`, an optional external URL, and a position. A file resource references the existing private local media library; a link resource stores an HTTP or HTTPS URL. Deleting content deletes its resource rows, while deleting referenced media remains blocked.

## Admin flow

The editor gains a "المواد المصاحبة" section with two actions:

- Upload one or more files from the administrator's device through the existing protected media endpoint.
- Add a titled HTTP or HTTPS link.

Uploaded files and links appear immediately in an ordered list and can be removed before saving. The client serializes the list into the content form. The server revalidates every entry and synchronizes it transactionally when content is created or updated; client data is never trusted directly.

Documents, presentations, spreadsheets, archives, images, PDFs, audio, and video are accepted. Executables and SVG remain rejected. The existing 256 MB application limit remains, subject to the hosting PHP upload limit.

## Public flow and access

When the content body is unlocked, the page displays all resources in saved order. File resources use the existing media delivery controller and download with their original filename. Link resources open safely in a new tab. Subscriber-only resources are inaccessible until the same access token that unlocks the parent content is valid.

## Validation and deletion

- At most 50 resources per content item.
- Titles are required and limited to 255 characters.
- File resources must reference a real media record.
- Link resources must use HTTP or HTTPS and are limited to 2048 characters.
- Media referenced by content body, cover, or resource cannot be deleted.
- Removing a resource detaches it from the content but leaves the media in the reusable media library.

## Verification

Feature tests cover file upload types, resource synchronization, invalid resource rejection, public rendering, gated media access, and deletion protection. The frontend production build must also succeed.

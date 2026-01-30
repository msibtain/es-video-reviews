# ES Video Reviews

A WordPress plugin that displays a review form with a 4-star rating and review text field. Submissions are saved to the **video_reviews** custom post type (the CPT must be registered elsewhere).

## Requirements

- The custom post type `video_reviews` must already be registered in your theme or another plugin. This plugin does **not** register the CPT.

## Installation

1. Copy the plugin folder into `wp-content/plugins/` (or upload as a zip and install via **Plugins → Add New**).
2. Activate **ES Video Reviews** under **Plugins**.

## Usage

Place the review form on any page or post using the shortcode:

```
[video_review_form]
```

The form includes:

- **Rating**: 4 clickable stars (1–4)
- **Your review**: Required text area
- **Submit review**: Saves a new post in the `video_reviews` CPT

### Stored data

- **Post type**: `video_reviews`
- **Post title**: e.g. "Review – 30/01/2025 at 2:30 pm"
- **Post content**: The review text
- **Post meta** `_video_review_rating`: The star rating (1–4)

After a successful submit, the user is redirected back and sees a "Thank you! Your review has been submitted." message.

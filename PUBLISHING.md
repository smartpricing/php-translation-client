# Publishing to Packagist

## Prerequisites

1. GitHub account
2. Packagist account (https://packagist.org)
3. GitHub repository for the package

## Step 1: Create GitHub Repository

1. Create a new repository on GitHub: `smartness/translation-client`
2. Initialize git in the package directory:

```bash
cd packages/smartness/translation-client
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin git@github.com:smartness/translation-client.git
git push -u origin main
```

## Step 2: Tag a Release

Create a version tag (following semantic versioning):

```bash
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0
```

## Step 3: Submit to Packagist

1. Go to https://packagist.org
2. Click "Submit" in the top menu
3. Enter your GitHub repository URL: `https://github.com/smartness/translation-client`
4. Click "Check"
5. If validation passes, click "Submit"

## Step 4: Set up Auto-Update Hook (Recommended)

### Option A: GitHub Webhook (Automatic)

Packagist will automatically suggest setting up a webhook. Follow their instructions.

### Option B: Manual (Alternative)

In your GitHub repository settings:
1. Go to Settings → Webhooks → Add webhook
2. Payload URL: `https://packagist.org/api/github?username=YOUR_PACKAGIST_USERNAME`
3. Content type: `application/json`
4. Secret: Your Packagist API token (from packagist.org/profile)
5. Events: "Just the push event"

## Step 5: Test Installation

In a test Laravel project:

```bash
composer require smartness/translation-client
```

## Releasing New Versions

1. Make your changes
2. Update version in composer.json if needed
3. Commit changes
4. Create a new tag:

```bash
git tag -a v1.0.1 -m "Release version 1.0.1"
git push origin v1.0.1
```

5. Packagist will auto-update (if webhook is configured)

## Semantic Versioning

Follow semantic versioning (https://semver.org):

- **MAJOR** (v2.0.0): Breaking changes
- **MINOR** (v1.1.0): New features, backward compatible
- **PATCH** (v1.0.1): Bug fixes, backward compatible

## Best Practices

1. **Always test before releasing**
2. **Update CHANGELOG.md** with each release
3. **Use semantic versioning**
4. **Write clear commit messages**
5. **Tag releases consistently**
6. **Keep README up to date**

## Checklist Before First Release

- [ ] All code is tested and working
- [ ] README.md is complete and accurate
- [ ] LICENSE.md is included
- [ ] composer.json has correct metadata
- [ ] Package name is available on Packagist
- [ ] GitHub repository is public
- [ ] Version tag is created
- [ ] Packagist submission is complete

## Troubleshooting

### Package not found after submission

- Wait a few minutes for Packagist to index
- Check that your repository is public
- Verify composer.json syntax is valid

### Auto-update not working

- Verify webhook is configured correctly
- Check webhook delivery in GitHub settings
- Manually update on Packagist as fallback

## Resources

- Packagist: https://packagist.org
- Composer documentation: https://getcomposer.org/doc/
- Semantic Versioning: https://semver.org

#!/bin/bash
#
# Script for travis-ci to sync apidoc dir from master branch to gh-pages branch
# based on :
# * https://github.com/steveklabnik/automatically_update_github_pages_with_travis_example
# * https://github.com/gemini-testing/gemini/pull/352/files

set -o errexit -o nounset

if [ "$TRAVIS_BRANCH" != "master" ] ; then
  echo "This commit was made against the $TRAVIS_BRANCH and not the master! No deploy!"
  exit 0
fi

if [ "$GH_TOKEN" == "" ] ; then
  echo "GH_TOKEN is not defined"
  exit 1
fi

rev=$(git rev-parse --short HEAD)
repo=$(git config --local --get-all remote.origin.url | awk -F'[:/]' 'NF && NF-1 {print ($(NF-1)"/"$NF)}')

echo -e "Starting to update gh-pages of ${repo} at ${rev}\n"
# on Travis, can't optimize by cloning itself and define remote as upstream
# travis> "attempt to fetch/clone from a shallow repository"
# So need to do a full clone
rm -Rf gh-pages
git clone -b gh-pages --single-branch https://${GH_TOKEN}@github.com/${repo} gh-pages > /dev/null
cd gh-pages

rsync -az --stats --delete --exclude .git --force ../build/asciidoc/html5/ .

git config --local user.email "travis@travis-ci.org"
git config --local user.name "Travis"
git add -A . > /dev/null
git commit -m "Travis build $TRAVIS_BUILD_NUMBER pushed to gh-pages at ${rev}"
git push --force --quiet origin gh-pages
cd ..
rm -Rf gh-pages

/**
 * Structured sample content — SAMPLE COPY throughout; realistic filler for
 * the owner to keep, cut, or rewrite. Everything on this page is plain data:
 * edit the text here and the pages update, no layout knowledge needed.
 * Long-form copy lives in the .md files next to this one.
 */

/** Home page "what we do" highlight blurbs. SAMPLE COPY — replace. */
export const HOME_HIGHLIGHTS = [
  {
    title: 'Strengthening churches',
    text: 'We walk alongside our member congregations — encouraging pastors, supporting church health, and helping churches of every size thrive.',
    href: '/ministries/',
    icon: 'church',
  },
  {
    title: 'Missions together',
    text: 'From Blount County to the ends of the earth, our churches accomplish together what none of us could do alone.',
    href: '/missions/',
    icon: 'globe',
  },
  {
    title: 'Caring for pastors',
    text: 'Ministry is demanding. We provide fellowship, counsel, and practical care for the shepherds who care for everyone else.',
    href: '/ministries/',
    icon: 'heart',
  },
  {
    title: 'Equipping for ministry',
    text: 'Training for VBS leaders, Sunday School teachers, deacons, and volunteers — practical help for the people doing the work.',
    href: '/events/',
    icon: 'book',
  },
] as const;

/** Ministries page cards. SAMPLE COPY — typical associational ministries. */
export const MINISTRIES = [
  {
    title: 'Church planting & revitalization',
    text: 'Helping established churches find fresh vision and coming alongside new works — because every community in Blount County needs a healthy, gospel-preaching church.',
  },
  {
    title: 'Pastor & staff care',
    text: 'Monthly pastor fellowships, confidential counsel, and encouragement for ministers and their families. No pastor in our association should walk alone.',
  },
  {
    title: 'Missions & disaster relief',
    text: 'Coordinating our churches for local outreach, state and national partnerships, and Alabama Baptist Disaster Relief when storms strike our neighbors.',
  },
  {
    title: 'VBS & children’s ministry support',
    text: 'Training, curriculum previews, and shared resources that help even the smallest church put on a great Vacation Bible School.',
  },
  {
    title: 'Men’s & women’s ministries',
    text: 'Associational gatherings, Bible studies, and service projects that connect believers across our churches.',
  },
  {
    title: 'Benevolence & community care',
    text: 'Churches working together to meet real needs — food, clothing, and crisis help — with the love of Christ in practical form.',
  },
] as const;

/** Sample event cards for the Events page. Clearly-sample; no real dates. */
export const SAMPLE_EVENTS = [
  {
    title: 'Associational Annual Meeting',
    when: 'Each fall — date announced on the calendar',
    where: 'Host church rotates among member churches',
    text: 'Messengers from all our churches gather for worship, reports, fellowship, and the business of the association.',
  },
  {
    title: 'Pastor Appreciation Lunch',
    when: 'Announced on the calendar',
    where: 'Association office, Oneonta',
    text: 'A meal and encouragement for the pastors and staff who serve our churches faithfully all year long.',
  },
  {
    title: 'VBS Training Workshop',
    when: 'Each spring — before VBS season',
    where: 'Host church announced on the calendar',
    text: 'Hands-on training for VBS directors, teachers, and volunteers from every member church.',
  },
] as const;

/** Volunteer opportunities for Missions / Get Involved. SAMPLE COPY. */
export const VOLUNTEER_OPPORTUNITIES = [
  {
    title: 'Disaster relief teams',
    text: 'Train with Alabama Baptist Disaster Relief and serve neighbors after storms — chainsaw crews, feeding units, cleanup, and chaplaincy.',
  },
  {
    title: 'Local outreach projects',
    text: 'Food drives, school supply collections, and community service days organized across our churches.',
  },
  {
    title: 'Mission trips',
    text: 'Churches partner on trips within Alabama, across North America, and internationally through Southern Baptist channels.',
  },
  {
    title: 'Serve your own church',
    text: 'The best place to start is right where you are — talk to your pastor about where help is needed most.',
  },
] as const;

/** Resources page sample document list. Links are placeholders. */
export const SAMPLE_RESOURCES = [
  { title: 'Annual meeting minutes', note: 'Most recent associational annual meeting', href: '#' },
  { title: 'Church profile update form', note: 'Keep your church’s directory listing current', href: '#' },
  { title: 'Ministry request form', note: 'Request associational support for your event or project', href: '#' },
  { title: 'Annual church profile (ACP) helps', note: 'Guides for completing your yearly report', href: '#' },
  { title: 'Giving report', note: 'Quarterly summary of cooperative giving', href: '#' },
  { title: 'Recommended links', note: 'Alabama Baptist State Convention, SBC, IMB, NAMB', href: '#' },
] as const;

/** Officers/staff placeholders for the About page. ⚠️ OWNER TO PROVIDE. */
export const OFFICERS = [
  { role: 'Associational Mission Strategist', name: 'Dale Wood', placeholder: false },
  { role: 'Moderator', name: 'Name coming soon', placeholder: true },
  { role: 'Clerk', name: 'Name coming soon', placeholder: true },
  { role: 'Treasurer', name: 'Name coming soon', placeholder: true },
] as const;
